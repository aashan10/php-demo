<?php

declare(strict_types=1);

namespace Elementary\Spark;

use Elementary\Template\Cigg\Engine;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionProperty;

abstract class SparkComponent
{
    protected array $data = [];
    protected array $rules = [];
    protected array $listeners = [];
    protected string $componentId;
    protected Engine $engine;
    protected ?LoggerInterface $logger = null;
    
    public function __construct(Engine $engine, ?LoggerInterface $logger = null)
    {
        $this->engine = $engine;
        $this->logger = $logger;
        $this->componentId = $this->generateComponentId();
        
        $this->log('debug', 'SparkComponent created', [
            'component' => get_class($this),
            'id' => $this->componentId
        ]);
        
        try {
            $this->mount();
            $this->log('debug', 'Component mounted successfully');
        } catch (\Throwable $e) {
            $this->log('error', 'Component mount failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException("Failed to mount component " . get_class($this) . ": " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Called when component is first created
     */
    protected function mount(): void
    {
        // Override in child classes
    }

    /**
     * Get the view for this component
     */
    abstract public function render(): string;
    
    /**
     * Get the compiled template path for this component
     */
    public function getCompiledTemplate(): string
    {
        // Convert class name to template path
        $className = get_class($this);
        $templateName = strtolower(str_replace(['App\\SparkComponents\\', 'Component'], ['', ''], $className));
        
        $templatePath = $this->engine->getViewsPath() . '/spark/' . $templateName . '.cigg';
        
        if (!file_exists($templatePath)) {
            throw new \Exception("SparkComponent template not found: {$templatePath}");
        }
        
        // Generate cache path
        $cacheKey = md5($templatePath . get_class($this));
        $cachePath = $this->engine->getCachePath() . '/spark_' . $cacheKey . '.php';
        
        // Compile if needed
        if (!file_exists($cachePath) || filemtime($templatePath) > filemtime($cachePath)) {
            $this->engine->compileTemplate($templatePath, $cachePath);
        }
        
        return $cachePath;
    }

    /**
     * Generate unique component ID
     */
    protected function generateComponentId(): string
    {
        return 'lw-' . uniqid() . '-' . time();
    }

    /**
     * Get component ID
     */
    public function getId(): string
    {
        return $this->componentId;
    }

    /**
     * Set component ID (for restoring state from requests)
     */
    public function setId(string $id): void
    {
        $this->componentId = $id;
    }

    /**
     * Set property value
     */
    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * Get property value
     */
    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Check if property exists
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Get all public properties that should be synced with frontend
     */
    public function getPublicProperties(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = [];
        
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $name = $property->getName();
                $properties[$name] = $this->$name ?? null;
            }
        }
        
        return array_merge($properties, $this->data);
    }

    /**
     * Sync properties from request
     */
    public function syncInput(array $updates): void
    {
        $this->log('debug', 'Syncing input data', ['updates' => $updates]);
        
        foreach ($updates as $property => $value) {
            if ($this->isPublicProperty($property)) {
                $oldValue = $this->$property ?? null;
                $this->$property = $value;
                
                $this->log('debug', 'Property updated', [
                    'property' => $property,
                    'old_value' => $oldValue,
                    'new_value' => $value
                ]);
            } else {
                $this->log('warning', 'Attempted to sync non-public property', [
                    'property' => $property,
                    'value' => $value,
                    'component' => get_class($this)
                ]);
                
                throw new \InvalidArgumentException("Property '{$property}' is not public and cannot be synced on component " . get_class($this));
            }
        }
    }

    /**
     * Check if property is public and can be synced
     */
    protected function isPublicProperty(string $property): bool
    {
        $reflection = new ReflectionClass($this);
        
        if ($reflection->hasProperty($property)) {
            return $reflection->getProperty($property)->isPublic();
        }
        
        return true; // Allow dynamic properties
    }

    /**
     * Handle method calls from frontend
     */
    public function callMethod(string $method, array $params = []): void
    {
        $this->log('debug', 'Method call requested', [
            'method' => $method,
            'params' => $params,
            'component' => get_class($this)
        ]);
        
        if (!method_exists($this, $method)) {
            $this->log('error', 'Method does not exist', [
                'method' => $method,
                'component' => get_class($this)
            ]);
            throw new \BadMethodCallException("Method '{$method}' does not exist on component " . get_class($this));
        }
        
        if (!$this->isPublicMethod($method)) {
            $this->log('error', 'Method is not public', [
                'method' => $method,
                'component' => get_class($this)
            ]);
            throw new \BadMethodCallException("Method '{$method}' is not public on component " . get_class($this));
        }
        
        try {
            $result = $this->$method(...$params);
            $this->log('debug', 'Method executed successfully', [
                'method' => $method,
                'result_type' => gettype($result)
            ]);
        } catch (\Throwable $e) {
            $this->log('error', 'Method execution failed', [
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException("Method '{$method}' failed on component " . get_class($this) . ": " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if method can be called from frontend
     */
    protected function isPublicMethod(string $method): bool
    {
        $reflection = new ReflectionClass($this);
        
        if (!$reflection->hasMethod($method)) {
            return false;
        }
        
        $reflectionMethod = $reflection->getMethod($method);
        return $reflectionMethod->isPublic() && !$reflectionMethod->isStatic();
    }

    /**
     * Validate component data
     */
    public function validate(): array
    {
        $errors = [];
        
        foreach ($this->rules as $property => $rules) {
            $value = $this->$property ?? null;
            $propertyRules = is_string($rules) ? explode('|', $rules) : $rules;
            
            foreach ($propertyRules as $rule) {
                if ($rule === 'required' && empty($value)) {
                    $errors[$property][] = "The {$property} field is required.";
                }
                
                if (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (strlen((string) $value) < $min) {
                        $errors[$property][] = "The {$property} field must be at least {$min} characters.";
                    }
                }
                
                if (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (strlen((string) $value) > $max) {
                        $errors[$property][] = "The {$property} field must not exceed {$max} characters.";
                    }
                }
                
                if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$property][] = "The {$property} field must be a valid email address.";
                }
            }
        }
        
        return $errors;
    }

    /**
     * Emit event to other components
     */
    protected function emit(string $event, mixed $data = null): void
    {
        // This will be handled by the SparkComponentManager
        SparkComponentManager::getInstance()->addEvent($event, $data);
    }

    /**
     * Get component state for frontend
     */
    public function getState(): array
    {
        return [
            'id' => $this->componentId,
            'name' => get_class($this), // Pass the component class name
            'data' => $this->getPublicProperties(),
            'checksum' => $this->generateChecksum()
        ];
    }

    /**
     * Generate checksum for security
     */
    public function generateChecksum(): string
    {
        // Use JSON encoding for compatibility with client-side
        return hash('sha256', json_encode($this->getPublicProperties()) . get_class($this));
    }

    /**
     * Verify checksum
     */
    public function verifyChecksum(string $checksum): bool
    {
        return hash_equals($this->generateChecksum(), $checksum);
    }

    /**
     * Render the component with wrapper using compiled template
     */
    public function toHtml(): string
    {
        $this->log('debug', 'Rendering component to HTML', [
            'component' => get_class($this),
            'id' => $this->componentId
        ]);
        
        try {
            // Get compiled template path
            $cachePath = $this->getCompiledTemplate();
            
            // Prepare component data (make public properties available)
            $componentData = $this->getPublicProperties();
            $componentData['__spark'] = $this;
            
            // Include compiled template directly (no nested ob_start)
            extract($componentData);
            ob_start();
            include $cachePath;
            $content = ob_get_clean();
            
            $this->log('debug', 'Component template included successfully', [
                'content_length' => strlen($content),
                'template_path' => $cachePath
            ]);
        } catch (\Throwable $e) {
            $this->log('error', 'Component render failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException("Failed to render component " . get_class($this) . ": " . $e->getMessage(), 0, $e);
        }
        
        $state = $this->getState();
        
        $attributes = [
            'wire:id' => $this->componentId,
            'wire:data' => base64_encode(json_encode($state))
        ];
        
        $attributeString = '';
        foreach ($attributes as $key => $value) {
            $attributeString .= ' ' . $key . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }
        
        $html = "<div{$attributeString}>{$content}</div>";
        
        $this->log('debug', 'Component HTML generated successfully', [
            'html_length' => strlen($html),
            'state_size' => strlen(json_encode($state)),
            'raw_content_length' => strlen($content)
        ]);
        
        return $html;
    }
    
    /**
     * Log a message if logger is available
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $context['spark_component'] = get_class($this);
            $context['component_id'] = $this->componentId;
            $this->logger->log($level, "[Spark] {$message}", $context);
        }
    }
}