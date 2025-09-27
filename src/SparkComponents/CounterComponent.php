<?php

declare(strict_types=1);

namespace App\SparkComponents;

use Elementary\Spark\SparkComponent;

class CounterComponent extends SparkComponent
{
    public int $count = 0;
    public string $message = 'Click the button to increment!';
    
    protected array $rules = [
        'message' => 'required|min:3|max:255'
    ];

    protected function mount(): void
    {
        // Initialize component with default values
        $this->count = 0;
        $this->message = 'Click the button to increment!';
    }

    public function increment(): void
    {
        $this->count++;
        $this->message = "Count is now {$this->count}!";
        
        if ($this->count === 10) {
            $this->emit('counter-milestone', ['count' => $this->count]);
        }
    }

    public function decrement(): void
    {
        if ($this->count > 0) {
            $this->count--;
            $this->message = $this->count === 0 
                ? 'Back to zero!' 
                : "Count is now {$this->count}!";
        }
    }

    public function reset(): void
    {
        $this->count = 0;
        $this->message = 'Counter reset! Click to start again.';
        $this->emit('counter-reset');
    }

    public function render(): string
    {
        // Use the compiled template system for rendering
        $cachePath = $this->getCompiledTemplate();
        
        // Prepare component data (make public properties available)
        $componentData = $this->getPublicProperties();
        
        // Include compiled template and capture output
        extract($componentData);
        ob_start();
        include $cachePath;
        return ob_get_clean();
    }
}
