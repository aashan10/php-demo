<?php

declare(strict_types=1);

namespace Elementary\Spark;

use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Template\Cigg\Engine;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class SparkManager
{
    private static ?self $instance = null;
    private array $components = [];
    private array $events = [];
    private array $registeredComponents = [];
    private ContainerInterface $container;
    private ?LoggerInterface $logger = null;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        
        try {
            $this->logger = $container->get(LoggerInterface::class);
        } catch (\Throwable $e) {
            // Logger not available, continue without logging
        }
        
        $this->log('debug', 'SparkManager initialized');
        self::$instance = $this;
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('SparkManager not initialized');
        }
        
        return self::$instance;
    }

    /**
     * Register a component class with a friendly name
     */
    public function registerComponent(string $name, string $className): void
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Component class {$className} does not exist");
        }

        if (!is_subclass_of($className, SparkComponent::class)) {
            throw new \InvalidArgumentException("Component {$className} must extend " . SparkComponent::class);
        }

        $this->registeredComponents[$name] = $className;
        
        $this->log('debug', 'Component registered', [
            'name' => $name,
            'class' => $className
        ]);
    }

    /**
     * Get component class by name
     */
    public function getComponentClass(string $name): ?string
    {
        return $this->registeredComponents[$name] ?? null;
    }

    /**
     * Check if component is registered
     */
    public function hasComponent(string $name): bool
    {
        return isset($this->registeredComponents[$name]);
    }

    /**
     * Get all registered components
     */
    public function getRegisteredComponents(): array
    {
        return $this->registeredComponents;
    }

    /**
     * Register a component instance
     */
    public function register(string $id, SparkComponent $component): void
    {
        $this->components[$id] = $component;
    }

    /**
     * Get registered component by ID
     */
    public function getComponent(string $id): ?SparkComponent
    {
        return $this->components[$id] ?? null;
    }

    /**
     * Create a new component instance by class name
     */
    public function createComponent(string $class, array $params = []): SparkComponent
    {
        $this->log('debug', 'Creating component', [
            'class' => $class,
            'params' => $params
        ]);
        
        // Basic validation (also done by middleware for AJAX requests)
        if (!class_exists($class) || !is_subclass_of($class, SparkComponent::class)) {
            throw new \InvalidArgumentException("Invalid component class: {$class}");
        }

        return $this->instantiateComponent($class, $params);
    }

    /**
     * Create a new component instance by registered name
     */
    public function createComponentByName(string $name, array $params = []): SparkComponent
    {
        $class = $this->getComponentClass($name);
        
        if (!$class) {
            throw new \InvalidArgumentException("Component '{$name}' is not registered");
        }

        $this->log('debug', 'Creating component by name', [
            'name' => $name,
            'class' => $class,
            'params' => $params
        ]);

        return $this->instantiateComponent($class, $params);
    }

    /**
     * Internal method to instantiate component
     */
    private function instantiateComponent(string $class, array $params): SparkComponent
    {

        try {
            $engine = $this->container->get(Engine::class);
            $this->log('debug', 'Engine retrieved from container');
        } catch (\Throwable $e) {
            $this->log('error', 'Failed to get Engine from container', [
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Failed to get Engine from container: " . $e->getMessage(), 0, $e);
        }

        try {
            // Create component
            $obj = new $class($engine);
            $this->log('debug', 'Component instance created', ['class' => $class]);
        } catch (\Throwable $e) {
            $this->log('error', 'Failed to create component', [
                'class' => $class,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Failed to create component {$class}: " . $e->getMessage(), 0, $e);
        }

        // Set parameters
        foreach ($params as $key => $value) {
            if (property_exists($obj, $key)) {
                $obj->$key = $value;
                $this->log('debug', 'Parameter set on component', [
                    'property' => $key,
                    'value' => $value
                ]);
            } else {
                $this->log('warning', 'Parameter property does not exist on component', [
                    'class' => $class,
                    'property' => $key
                ]);
            }
        }

        $this->register($obj->getId(), $obj);
        
        $this->log('debug', 'Component created and registered successfully', [
            'class' => $class,
            'id' => $obj->getId()
        ]);
        
        return $obj;
    }

    /**
     * Handle AJAX request from frontend
     * Note: Validation is handled by middleware (ValidateJsonPayload, ValidateSparkRequest)
     */
    public function handleRequest(Request $request): Response
    {
        $this->log('debug', 'Handling Spark AJAX request', [
            'method' => $request->method(),
            'uri' => $request->uri()
        ]);
        
        // Get validated payload from middleware
        $payload = $request->attributes->get('spark_payload');
        
        if (!$payload) {
            $this->log('error', 'Spark payload not found - middleware not applied?');
            return new Response(500, json_encode([
                'error' => 'Internal error: Request not processed by Spark middleware'
            ]), ['Content-Type' => 'application/json']);
        }
        
        $this->log('debug', 'Request payload received from middleware', [
            'componentName' => $payload['componentName'],
            'componentId' => $payload['componentId'],
            'hasUpdates' => isset($payload['updates']),
            'hasMethod' => isset($payload['method'])
        ]);

        try {
            // Create fresh component instance by class name (validation done by middleware)
            $component = $this->createComponent($payload['componentName']);
            
            // Restore component ID and data state from serverMemo
            $component->setId($payload['componentId']);
            if (isset($payload['serverMemo']['data'])) {
                $component->syncInput($payload['serverMemo']['data']);
            }
            
            // Apply any pending updates to bring component to client's current state
            if (isset($payload['updates'])) {
                $component->syncInput($payload['updates']);
            }
            
            $this->log('debug', 'Component recreated for request', [
                'componentId' => $payload['componentId'],
                'componentClass' => get_class($component),
                'serverMemoData' => $payload['serverMemo']['data'] ?? null,
                'updates' => $payload['updates'] ?? null
            ]);
        } catch (\Throwable $e) {
            $this->log('error', 'Failed to recreate component', [
                'componentName' => $payload['componentName'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new Response(500, json_encode([
                'error' => 'Failed to recreate component: ' . $e->getMessage(),
                'componentName' => $payload['componentName']
            ]), ['Content-Type' => 'application/json']);
        }

        // Note: Checksum verification is now handled by ValidateSparkChecksum middleware

        try {
            // Call method if specified
            if (isset($payload['method'])) {
                $component->callMethod($payload['method'], $payload['params'] ?? []);
            }

            // Validate if needed
            $errors = [];
            if (isset($payload['validate']) && $payload['validate']) {
                $errors = $component->validate();
                $this->log('debug', 'Validation performed', ['errors' => $errors]);
            }

            // Prepare response  
            $response = [
                'html' => $component->toHtml(),
                'data' => $component->getPublicProperties(),
                'checksum' => $component->getState()['checksum'],
                'events' => $this->getEvents(),
                'errors' => $errors
            ];

            $this->log('debug', 'Response prepared', [
                'html_length' => strlen($response['html']),
                'data_keys' => array_keys($response['data']),
                'html_preview' => substr($response['html'], 0, 200) . (strlen($response['html']) > 200 ? '...' : '')
            ]);

            $this->clearEvents();

            $this->log('debug', 'Spark request handled successfully', [
                'componentId' => $payload['componentId'],
                'eventsCount' => count($response['events']),
                'errorsCount' => count($errors)
            ]);

            return new Response(200, json_encode($response), [
                'Content-Type' => 'application/json'
            ]);
            
        } catch (\Throwable $e) {
            $this->log('error', 'Error processing Spark request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'componentId' => $payload['componentId']
            ]);
            
            return new Response(500, json_encode([
                'error' => 'Internal server error: ' . $e->getMessage(),
                'componentId' => $payload['componentId']
            ]), [
                'Content-Type' => 'application/json'
            ]);
        }
    }

    /**
     * Add an event to be sent to frontend
     */
    public function addEvent(string $event, mixed $data = null): void
    {
        $this->events[] = [
            'name' => $event,
            'data' => $data,
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Get all pending events
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Clear all events
     */
    public function clearEvents(): void
    {
        $this->events = [];
    }

    /**
     * Render a component by class name
     */
    public function renderComponent(string $class, array $params = []): string
    {
        $this->log('debug', 'Rendering component', [
            'class' => $class,
            'params' => $params
        ]);
        
        try {
            $component = $this->createComponent($class, $params);
            $html = $component->toHtml();
            
            $this->log('debug', 'Component rendered successfully', [
                'class' => $class,
                'html_length' => strlen($html)
            ]);
            
            return $html;
        } catch (\Throwable $e) {
            $this->log('error', 'Failed to render component', [
                'class' => $class,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \RuntimeException("Failed to render component {$class}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Render a component by registered name
     */
    public function renderComponentByName(string $name, array $params = []): string
    {
        $this->log('debug', 'Rendering component by name', [
            'name' => $name,
            'params' => $params
        ]);
        
        try {
            $component = $this->createComponentByName($name, $params);
            $html = $component->toHtml();
            
            $this->log('debug', 'Component rendered successfully by name', [
                'name' => $name,
                'class' => get_class($component),
                'html_length' => strlen($html)
            ]);
            
            return $html;
        } catch (\Throwable $e) {
            $this->log('error', 'Failed to render component by name', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \RuntimeException("Failed to render component '{$name}': " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Static helper to render a component by name (convenience method)
     */
    public static function render(string $name, array $params = []): string
    {
        return self::getInstance()->renderComponentByName($name, $params);
    }
    
    /**
     * Static helper to create a component by name (convenience method)
     */
    public static function make(string $name, array $params = []): SparkComponent
    {
        return self::getInstance()->createComponentByName($name, $params);
    }
    
    /**
     * Log a message if logger is available
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log($level, "[SparkManager] {$message}", $context);
        }
    }
}
