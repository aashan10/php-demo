# Spark Examples

Real-world examples and patterns for building Spark components.

## Table of Contents

1. [Basic Examples](#basic-examples)
2. [Form Components](#form-components)
3. [Data Display Components](#data-display-components)
4. [Interactive Components](#interactive-components)
5. [Advanced Patterns](#advanced-patterns)
6. [Real-World Applications](#real-world-applications)

## Basic Examples

### Simple Counter

The classic reactive counter component:

```php
<?php
// src/SparkComponents/CounterComponent.php
namespace App\SparkComponents;

use Elementary\Spark\SparkComponent;

class CounterComponent extends SparkComponent
{
    public int $count = 0;
    public string $message = 'Click to increment!';
    
    protected function mount(): void
    {
        $this->count = 0;
    }
    
    public function increment(): void
    {
        $this->count++;
        $this->message = $this->count === 1 
            ? "You've clicked once!" 
            : "You've clicked {$this->count} times!";
    }
    
    public function decrement(): void
    {
        if ($this->count > 0) {
            $this->count--;
            $this->message = $this->count === 0 
                ? 'Back to zero!' 
                : "Count is now {$this->count}";
        }
    }
    
    public function reset(): void
    {
        $this->count = 0;
        $this->message = 'Counter reset!';
    }
    
    public function render(): string
    {
        $cachePath = $this->getCompiledTemplate();
        $componentData = $this->getPublicProperties();
        extract($componentData);
        ob_start();
        include $cachePath;
        return ob_get_clean();
    }
}
```

```html
<!-- templates/spark/counter.cigg -->
<div class="counter-widget bg-white rounded-lg shadow p-6">
    <div class="text-center mb-4">
        <h3 class="text-xl font-bold text-gray-800">Counter</h3>
        <p class="text-gray-600">{{ $message }}</p>
    </div>
    
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full">
            <span class="text-2xl font-bold text-blue-600">{{ $count }}</span>
        </div>
    </div>
    
    <div class="flex space-x-2">
        <button wire:click="decrement" 
                class="flex-1 bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded"
                @if($count <= 0) disabled @endif>
            -
        </button>
        <button wire:click="increment" 
                class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded">
            +
        </button>
        <button wire:click="reset" 
                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
            Reset
        </button>
    </div>
</div>
```

### Hello World with Input

```php
<?php
// src/SparkComponents/HelloWorldComponent.php
namespace App\SparkComponents;

use Elementary\Spark\SparkComponent;

class HelloWorldComponent extends SparkComponent
{
    public string $name = '';
    public string $greeting = 'Hello';
    
    public function updateGreeting(): void
    {
        if (trim($this->name) === '') {
            $this->greeting = 'Hello';
        } else {
            $this->greeting = "Hello, {$this->name}!";
        }
    }
    
    public function render(): string
    {
        $cachePath = $this->getCompiledTemplate();
        $componentData = $this->getPublicProperties();
        extract($componentData);
        ob_start();
        include $cachePath;
        return ob_get_clean();
    }
}
```

```html
<!-- templates/spark/helloworld.cigg -->
<div class="hello-world bg-gray-50 p-4 rounded">
    <h2 class="text-lg font-semibold mb-3">{{ $greeting }}</h2>
    
    <div class="flex space-x-2">
        <input type="text" 
               wire:model="name" 
               wire:blur="updateGreeting"
               placeholder="Enter your name"
               class="flex-1 px-3 py-2 border rounded">
        <button wire:click="updateGreeting" 
                class="bg-blue-500 text-white px-4 py-2 rounded">
            Greet
        </button>
    </div>
</div>
```

## Form Components

### Contact Form

```php
<?php
// src/SparkComponents/Forms/ContactFormComponent.php
namespace App\SparkComponents\Forms;

use Elementary\Spark\SparkComponent;

class ContactFormComponent extends SparkComponent
{
    public string $name = '';
    public string $email = '';
    public string $subject = '';
    public string $message = '';
    public bool $submitted = false;
    public bool $submitting = false;
    
    protected array $rules = [
        'name' => 'required|min:2|max:100',
        'email' => 'required|email|max:255',
        'subject' => 'required|min:5|max:200',
        'message' => 'required|min:10|max:1000',
    ];
    
    public function submit(): void
    {
        $this->submitting = true;
        
        $errors = $this->validate();
        
        if (empty($errors)) {
            // Simulate sending email
            $this->sendEmail();
            
            $this->submitted = true;
            $this->reset();
            $this->emit('contact-form-submitted', [
                'name' => $this->name,
                'email' => $this->email
            ]);
        }
        
        $this->submitting = false;
    }
    
    public function reset(): void
    {
        $this->name = '';
        $this->email = '';
        $this->subject = '';
        $this->message = '';
        $this->submitted = false;
    }
    
    private function sendEmail(): void
    {
        // Email sending logic here
        // For demo purposes, just sleep briefly
        usleep(500000); // 0.5 seconds
    }
    
    public function render(): string
    {
        $cachePath = $this->getCompiledTemplate();
        $componentData = $this->getPublicProperties();
        extract($componentData);
        ob_start();
        include $cachePath;
        return ob_get_clean();
    }
}
```

```html
<!-- templates/spark/forms/contactform.cigg -->
<div class="contact-form bg-white rounded-lg shadow-lg p-6">
    <h2 class="text-2xl font-bold mb-6">Contact Us</h2>
    
    @if($submitted)
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <strong>Thank you!</strong> Your message has been sent successfully.
            <button wire:click="reset" class="float-right text-sm underline">
                Send another message
            </button>
        </div>
    @else
        <form wire:submit="submit" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                    <input type="text" 
                           wire:model="name" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Your full name">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                    <input type="email" 
                           wire:model="email" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="your@email.com">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Subject *</label>
                <input type="text" 
                       wire:model="subject" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Message subject">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Message *</label>
                <textarea wire:model="message" 
                          rows="5"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Your message..."></textarea>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md disabled:opacity-50"
                        @if($submitting) disabled @endif>
                    @if($submitting)
                        Sending...
                    @else
                        Send Message
                    @endif
                </button>
            </div>
        </form>
    @endif
</div>
```

### Dynamic Form Builder

```php
<?php
// src/SparkComponents/Forms/DynamicFormComponent.php
namespace App\SparkComponents\Forms;

use Elementary\Spark\SparkComponent;

class DynamicFormComponent extends SparkComponent
{
    public array $fields = [];
    public array $values = [];
    public array $errors = [];
    public string $submitLabel = 'Submit';
    
    protected function mount(): void
    {
        // Initialize default form structure
        if (empty($this->fields)) {
            $this->fields = [
                [
                    'name' => 'name',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                    'placeholder' => 'Enter your name'
                ],
                [
                    'name' => 'email',
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true,
                    'placeholder' => 'Enter your email'
                ]
            ];
        }
        
        // Initialize values
        foreach ($this->fields as $field) {
            if (!isset($this->values[$field['name']])) {
                $this->values[$field['name']] = '';
            }
        }
    }
    
    public function addField(): void
    {
        $this->fields[] = [
            'name' => 'field_' . (count($this->fields) + 1),
            'type' => 'text',
            'label' => 'New Field',
            'required' => false,
            'placeholder' => ''
        ];
        
        $this->updateValues();
    }
    
    public function removeField(int $index): void
    {
        if (isset($this->fields[$index])) {
            $fieldName = $this->fields[$index]['name'];
            unset($this->fields[$index]);
            unset($this->values[$fieldName]);
            
            // Re-index array
            $this->fields = array_values($this->fields);
        }
    }
    
    public function updateFieldProperty(int $index, string $property, string $value): void
    {
        if (isset($this->fields[$index])) {
            $this->fields[$index][$property] = $value;
            
            if ($property === 'name') {
                $this->updateValues();
            }
        }
    }
    
    public function submit(): void
    {
        $this->errors = $this->validateForm();
        
        if (empty($this->errors)) {
            $this->emit('form-submitted', [
                'fields' => $this->fields,
                'values' => $this->values
            ]);
        }
    }
    
    private function updateValues(): void
    {
        $newValues = [];
        foreach ($this->fields as $field) {
            $name = $field['name'];
            $newValues[$name] = $this->values[$name] ?? '';
        }
        $this->values = $newValues;
    }
    
    private function validateForm(): array
    {
        $errors = [];
        
        foreach ($this->fields as $field) {
            $name = $field['name'];
            $value = $this->values[$name] ?? '';
            
            if ($field['required'] && empty(trim($value))) {
                $errors[$name] = 'This field is required';
            }
            
            if ($field['type'] === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = 'Please enter a valid email address';
            }
        }
        
        return $errors;
    }
    
    public function render(): string
    {
        $cachePath = $this->getCompiledTemplate();
        $componentData = $this->getPublicProperties();
        extract($componentData);
        ob_start();
        include $cachePath;
        return ob_get_clean();
    }
}
```

## Data Display Components

### User List with Pagination

```php
<?php
// src/SparkComponents/UserListComponent.php
namespace App\SparkComponents;

use Elementary\Spark\SparkComponent;

class UserListComponent extends SparkComponent
{
    public array $users = [];
    public int $currentPage = 1;
    public int $perPage = 10;
    public int $totalUsers = 0;
    public string $searchTerm = '';
    public string $sortBy = 'name';
    public string $sortDirection = 'asc';
    public bool $loading = false;
    
    protected function mount(): void
    {
        $this->loadUsers();
    }
    
    public function search(): void
    {
        $this->currentPage = 1;
        $this->loadUsers();
    }
    
    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        
        $this->loadUsers();
    }
    
    public function goToPage(int $page): void
    {
        $this->currentPage = $page;
        $this->loadUsers();
    }
    
    public function deleteUser(int $userId): void
    {
        // Remove user from array
        $this->users = array_filter($this->users, fn($user) => $user['id'] !== $userId);
        $this->totalUsers--;
        
        $this->emit('user-deleted', ['userId' => $userId]);
    }
    
    private function loadUsers(): void
    {
        $this->loading = true;
        
        // Simulate API call
        usleep(300000); // 0.3 seconds
        
        // Mock data
        $allUsers = $this->generateMockUsers();
        
        // Apply search filter
        if (!empty($this->searchTerm)) {
            $allUsers = array_filter($allUsers, function($user) {
                return stripos($user['name'], $this->searchTerm) !== false ||
                       stripos($user['email'], $this->searchTerm) !== false;
            });
        }
        
        // Apply sorting
        usort($allUsers, function($a, $b) {
            $valueA = $a[$this->sortBy] ?? '';
            $valueB = $b[$this->sortBy] ?? '';
            
            $comparison = strcmp($valueA, $valueB);
            return $this->sortDirection === 'desc' ? -$comparison : $comparison;
        });
        
        $this->totalUsers = count($allUsers);
        
        // Apply pagination
        $offset = ($this->currentPage - 1) * $this->perPage;
        $this->users = array_slice($allUsers, $offset, $this->perPage);
        
        $this->loading = false;
    }
    
    private function generateMockUsers(): array
    {
        $names = ['John Doe', 'Jane Smith', 'Bob Johnson', 'Alice Brown', 'Charlie Wilson'];
        $domains = ['example.com', 'test.org', 'demo.net'];
        
        $users = [];
        for ($i = 1; $i <= 50; $i++) {
            $name = $names[array_rand($names)];
            $email = strtolower(str_replace(' ', '.', $name)) . $i . '@' . $domains[array_rand($domains)];
            
            $users[] = [
                'id' => $i,
                'name' => $name . ' ' . $i,
                'email' => $email,
                'created_at' => date('Y-m-d', strtotime("-{$i} days"))
            ];
        }
        
        return $users;
    }
    
    public function getTotalPages(): int
    {
        return ceil($this->totalUsers / $this->perPage);
    }
    
    public function render(): string
    {
        $cachePath = $this->getCompiledTemplate();
        $componentData = $this->getPublicProperties();
        extract($componentData);
        ob_start();
        include $cachePath;
        return ob_get_clean();
    }
}
```

```html
<!-- templates/spark/userlist.cigg -->
<div class="user-list bg-white rounded-lg shadow">
    <!-- Search Bar -->
    <div class="p-4 border-b">
        <div class="flex space-x-4">
            <input type="text" 
                   wire:model="searchTerm" 
                   wire:blur="search"
                   placeholder="Search users..."
                   class="flex-1 px-3 py-2 border rounded-md">
            <button wire:click="search" 
                    class="bg-blue-500 text-white px-4 py-2 rounded-md">
                Search
            </button>
        </div>
    </div>
    
    <!-- Loading State -->
    @if($loading)
        <div class="p-8 text-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
            <p class="mt-2 text-gray-600">Loading users...</p>
        </div>
    @else
        <!-- Users Table -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left">
                            <button wire:click="sort('name')" class="flex items-center space-x-1">
                                <span>Name</span>
                                @if($sortBy === 'name')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left">
                            <button wire:click="sort('email')" class="flex items-center space-x-1">
                                <span>Email</span>
                                @if($sortBy === 'email')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left">
                            <button wire:click="sort('created_at')" class="flex items-center space-x-1">
                                <span>Created</span>
                                @if($sortBy === 'created_at')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">{{ $user['name'] }}</td>
                            <td class="px-4 py-3">{{ $user['email'] }}</td>
                            <td class="px-4 py-3">{{ $user['created_at'] }}</td>
                            <td class="px-4 py-3">
                                <button wire:click="deleteUser({{ $user['id'] }})" 
                                        class="text-red-600 hover:text-red-800">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($this->getTotalPages() > 1)
            <div class="px-4 py-3 border-t bg-gray-50 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing {{ (($currentPage - 1) * $perPage) + 1 }} to 
                    {{ min($currentPage * $perPage, $totalUsers) }} of 
                    {{ $totalUsers }} results
                </div>
                
                <div class="flex space-x-1">
                    @if($currentPage > 1)
                        <button wire:click="goToPage({{ $currentPage - 1 }})" 
                                class="px-3 py-2 text-sm border rounded">
                            Previous
                        </button>
                    @endif
                    
                    @for($i = 1; $i <= $this->getTotalPages(); $i++)
                        <button wire:click="goToPage({{ $i }})" 
                                class="px-3 py-2 text-sm border rounded {{ $i === $currentPage ? 'bg-blue-500 text-white' : '' }}">
                            {{ $i }}
                        </button>
                    @endfor
                    
                    @if($currentPage < $this->getTotalPages())
                        <button wire:click="goToPage({{ $currentPage + 1 }})" 
                                class="px-3 py-2 text-sm border rounded">
                            Next
                        </button>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>
```

## Interactive Components

### Todo List Application

```php
<?php
// src/SparkComponents/TodoListComponent.php
namespace App\SparkComponents;

use Elementary\Spark\SparkComponent;

class TodoListComponent extends SparkComponent
{
    public array $todos = [];
    public string $newTodo = '';
    public string $filter = 'all'; // 'all', 'active', 'completed'
    
    protected function mount(): void
    {
        $this->todos = [
            [
                'id' => uniqid(),
                'text' => 'Learn Spark components',
                'completed' => true,
                'created_at' => time() - 3600
            ],
            [
                'id' => uniqid(),
                'text' => 'Build awesome features',
                'completed' => false,
                'created_at' => time() - 1800
            ]
        ];
    }
    
    public function addTodo(): void
    {
        $text = trim($this->newTodo);
        
        if (!empty($text)) {
            $this->todos[] = [
                'id' => uniqid(),
                'text' => $text,
                'completed' => false,
                'created_at' => time()
            ];
            
            $this->newTodo = '';
            $this->emit('todo-added', ['text' => $text]);
        }
    }
    
    public function toggleTodo(string $id): void
    {
        foreach ($this->todos as &$todo) {
            if ($todo['id'] === $id) {
                $todo['completed'] = !$todo['completed'];
                
                $this->emit('todo-toggled', [
                    'id' => $id,
                    'completed' => $todo['completed']
                ]);
                break;
            }
        }
    }
    
    public function removeTodo(string $id): void
    {
        $this->todos = array_filter($this->todos, fn($todo) => $todo['id'] !== $id);
        $this->emit('todo-removed', ['id' => $id]);
    }
    
    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }
    
    public function clearCompleted(): void
    {
        $completedCount = count(array_filter($this->todos, fn($todo) => $todo['completed']));
        $this->todos = array_filter($this->todos, fn($todo) => !$todo['completed']);
        
        $this->emit('todos-cleared', ['count' => $completedCount]);
    }
    
    public function getFilteredTodos(): array
    {
        return match($this->filter) {
            'active' => array_filter($this->todos, fn($todo) => !$todo['completed']),
            'completed' => array_filter($this->todos, fn($todo) => $todo['completed']),
            default => $this->todos
        };
    }
    
    public function getActiveCount(): int
    {
        return count(array_filter($this->todos, fn($todo) => !$todo['completed']));
    }
    
    public function getCompletedCount(): int
    {
        return count(array_filter($this->todos, fn($todo) => $todo['completed']));
    }
    
    public function render(): string
    {
        $cachePath = $this->getCompiledTemplate();
        $componentData = $this->getPublicProperties();
        extract($componentData);
        ob_start();
        include $cachePath;
        return ob_get_clean();
    }
}
```

```html
<!-- templates/spark/todolist.cigg -->
<div class="todo-app bg-white rounded-lg shadow-lg max-w-md mx-auto">
    <div class="p-6">
        <h1 class="text-2xl font-bold text-center mb-6">Todo List</h1>
        
        <!-- Add New Todo -->
        <form wire:submit="addTodo" class="mb-6">
            <div class="flex space-x-2">
                <input type="text" 
                       wire:model="newTodo" 
                       placeholder="What needs to be done?"
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit" 
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">
                    Add
                </button>
            </div>
        </form>
        
        <!-- Filter Buttons -->
        <div class="flex space-x-1 mb-4">
            <button wire:click="setFilter('all')" 
                    class="px-3 py-1 text-sm rounded {{ $filter === 'all' ? 'bg-blue-500 text-white' : 'bg-gray-200' }}">
                All ({{ count($todos) }})
            </button>
            <button wire:click="setFilter('active')" 
                    class="px-3 py-1 text-sm rounded {{ $filter === 'active' ? 'bg-blue-500 text-white' : 'bg-gray-200' }}">
                Active ({{ $this->getActiveCount() }})
            </button>
            <button wire:click="setFilter('completed')" 
                    class="px-3 py-1 text-sm rounded {{ $filter === 'completed' ? 'bg-blue-500 text-white' : 'bg-gray-200' }}">
                Completed ({{ $this->getCompletedCount() }})
            </button>
        </div>
        
        <!-- Todo List -->
        <div class="space-y-2 mb-4">
            @foreach($this->getFilteredTodos() as $todo)
                <div class="flex items-center space-x-3 p-3 border rounded-md {{ $todo['completed'] ? 'bg-gray-50' : 'bg-white' }}">
                    <input type="checkbox" 
                           wire:click="toggleTodo('{{ $todo['id'] }}')"
                           {{ $todo['completed'] ? 'checked' : '' }}
                           class="w-4 h-4">
                    <span class="flex-1 {{ $todo['completed'] ? 'line-through text-gray-500' : '' }}">
                        {{ $todo['text'] }}
                    </span>
                    <button wire:click="removeTodo('{{ $todo['id'] }}')" 
                            class="text-red-500 hover:text-red-700">
                        ×
                    </button>
                </div>
            @endforeach
            
            @if(empty($this->getFilteredTodos()))
                <div class="text-center text-gray-500 py-8">
                    @if($filter === 'all')
                        No todos yet. Add one above!
                    @elseif($filter === 'active')
                        No active todos. Great job!
                    @else
                        No completed todos yet.
                    @endif
                </div>
            @endif
        </div>
        
        <!-- Actions -->
        @if(!empty($todos))
            <div class="flex justify-between items-center text-sm text-gray-600">
                <span>{{ $this->getActiveCount() }} item(s) left</span>
                
                @if($this->getCompletedCount() > 0)
                    <button wire:click="clearCompleted" 
                            class="text-red-500 hover:text-red-700">
                        Clear completed
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>
```

This comprehensive documentation provides everything needed to understand and use the Spark reactive component system effectively!