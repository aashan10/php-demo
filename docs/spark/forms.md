# Form Handling

This guide covers comprehensive form handling in Spark components, including data binding, validation, submission, and advanced form patterns.

## Table of Contents

1. [Form Basics](#form-basics)
2. [Data Binding](#data-binding)
3. [Form Submission](#form-submission)
4. [Input Types](#input-types)
5. [File Uploads](#file-uploads)
6. [Form Validation](#form-validation)
7. [Dynamic Forms](#dynamic-forms)
8. [Advanced Patterns](#advanced-patterns)

## Form Basics

### Simple Form Component

```php
<?php
namespace App\SparkComponents;

use Elementary\Spark\SparkComponent;
use Elementary\Http\Request;

class ContactFormComponent extends SparkComponent
{
    // Form fields
    public string $name = '';
    public string $email = '';
    public string $subject = '';
    public string $message = '';
    
    // Form state
    public bool $submitted = false;
    public bool $submitting = false;
    
    // Validation rules
    protected array $rules = [
        'name' => 'required|min:2|max:50',
        'email' => 'required|email',
        'subject' => 'required|min:5|max:100',
        'message' => 'required|min:10|max:1000'
    ];
    
    public function submit(Request $request): void
    {
        $this->submitting = true;
        
        // Validate form data
        $errors = $this->validate();
        
        if (empty($errors)) {
            // Process form submission
            $this->processContactForm();
            $this->submitted = true;
            $this->submitting = false;
            
            // Emit success event
            $this->emit('contact-form-submitted', [
                'name' => $this->name,
                'email' => $this->email,
                'subject' => $this->subject
            ]);
        } else {
            $this->submitting = false;
            // Validation errors will be displayed automatically
        }
    }
    
    private function processContactForm(): void
    {
        // Send email, save to database, etc.
        // This is where you'd implement your business logic
    }
    
    public function reset(): void
    {
        $this->name = '';
        $this->email = '';
        $this->subject = '';
        $this->message = '';
        $this->submitted = false;
        $this->submitting = false;
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

### Form Template

```html
<!-- templates/spark/contactform.cigg -->
<div class="contact-form">
    @if($submitted)
        <div class="success-message">
            <h3>Thank You!</h3>
            <p>Your message has been sent successfully. We'll get back to you soon.</p>
            <button spark:click="reset" class="btn-secondary">Send Another Message</button>
        </div>
    @else
        <form spark:submit="submit" class="space-y-4">
            <div class="form-group">
                <label for="name">Name *</label>
                <input type="text" 
                       id="name"
                       spark:model="name" 
                       spark:on-blur="render"
                       class="form-input"
                       placeholder="Your full name">
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" 
                       id="email"
                       spark:model="email"
                       spark:on-blur="render"
                       class="form-input"
                       placeholder="your@email.com">
            </div>
            
            <div class="form-group">
                <label for="subject">Subject *</label>
                <input type="text" 
                       id="subject"
                       spark:model="subject"
                       class="form-input"
                       placeholder="What is this about?">
            </div>
            
            <div class="form-group">
                <label for="message">Message *</label>
                <textarea id="message"
                          spark:model="message"
                          rows="6"
                          class="form-input"
                          placeholder="Your message here..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" 
                        class="btn-primary"
                        {{ $submitting ? 'disabled' : '' }}>
                    {{ $submitting ? 'Sending...' : 'Send Message' }}
                </button>
                
                <button type="button" 
                        spark:click="reset"
                        class="btn-secondary">
                    Reset Form
                </button>
            </div>
        </form>
    @endif
</div>
```

## Data Binding

### Two-Way Data Binding

Spark provides automatic two-way data binding with `spark:model`:

```php
class FormBindingComponent extends SparkComponent
{
    // Text inputs
    public string $textInput = '';
    public string $emailInput = '';
    public string $passwordInput = '';
    
    // Numeric inputs
    public int $numberInput = 0;
    public float $rangeInput = 50.0;
    
    // Boolean inputs
    public bool $checkboxInput = false;
    public bool $toggleInput = true;
    
    // Select inputs
    public string $selectInput = '';
    public array $multiSelectInput = [];
    
    // Advanced inputs
    public string $dateInput = '';
    public string $timeInput = '';
    public string $colorInput = '#000000';
}
```

```html
<!-- Data binding examples -->
<form>
    <!-- Text inputs -->
    <input type="text" spark:model="textInput" placeholder="Text input">
    <input type="email" spark:model="emailInput" placeholder="Email input">
    <input type="password" spark:model="passwordInput" placeholder="Password">
    
    <!-- Numeric inputs -->
    <input type="number" spark:model="numberInput" min="0" max="100">
    <input type="range" spark:model="rangeInput" min="0" max="100" step="0.1">
    
    <!-- Boolean inputs -->
    <input type="checkbox" spark:model="checkboxInput"> Checkbox
    <input type="checkbox" spark:model="toggleInput"> Toggle
    
    <!-- Select inputs -->
    <select spark:model="selectInput">
        <option value="">Choose...</option>
        <option value="option1">Option 1</option>
        <option value="option2">Option 2</option>
    </select>
    
    <select spark:model="multiSelectInput" multiple>
        <option value="value1">Value 1</option>
        <option value="value2">Value 2</option>
        <option value="value3">Value 3</option>
    </select>
    
    <!-- Advanced inputs -->
    <input type="date" spark:model="dateInput">
    <input type="time" spark:model="timeInput">
    <input type="color" spark:model="colorInput">
</form>

<!-- Display current values -->
<div class="debug-info">
    <h4>Current Values:</h4>
    <pre>{{ json_encode($this->getPublicProperties(), JSON_PRETTY_PRINT) }}</pre>
</div>
```

### Real-time Updates

Use `spark:on-*` events for real-time form updates:

```php
class RealTimeFormComponent extends SparkComponent
{
    public string $searchTerm = '';
    public array $searchResults = [];
    public int $characterCount = 0;
    public bool $isValid = true;
    
    protected function updated(string $property, mixed $value): void
    {
        if ($property === 'searchTerm') {
            $this->characterCount = strlen($value);
            $this->isValid = $this->characterCount >= 3;
            
            if ($this->isValid) {
                $this->searchResults = $this->performSearch($value);
            } else {
                $this->searchResults = [];
            }
        }
    }
    
    private function performSearch(string $term): array
    {
        // Simulate search logic
        return [
            "Result for: {$term}",
            "Another result for: {$term}",
            "Third result for: {$term}"
        ];
    }
}
```

```html
<!-- Real-time search form -->
<div class="search-form">
    <div class="form-group">
        <label>Search</label>
        <input type="text" 
               spark:model="searchTerm"
               spark:on-input="render"
               placeholder="Type to search..."
               class="form-input {{ $isValid ? 'valid' : 'invalid' }}">
        
        <div class="form-help">
            {{ $characterCount }} characters
            @if(!$isValid && $characterCount > 0)
                <span class="text-red">(minimum 3 characters)</span>
            @endif
        </div>
    </div>
    
    @if(!empty($searchResults))
        <div class="search-results">
            <h4>Results:</h4>
            <ul>
                @foreach($searchResults as $result)
                    <li>{{ $result }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
```

## Form Submission

### Basic Form Submission

```php
class UserRegistrationComponent extends SparkComponent
{
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $confirmPassword = '';
    public bool $agreeToTerms = false;
    
    public bool $isSubmitting = false;
    public bool $isSubmitted = false;
    public ?string $submitError = null;
    
    protected array $rules = [
        'username' => 'required|min:3|max:20|unique:users',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:8',
        'confirmPassword' => 'required|same:password',
        'agreeToTerms' => 'accepted'
    ];
    
    public function register(): void
    {
        $this->isSubmitting = true;
        $this->submitError = null;
        
        try {
            $errors = $this->validate();
            
            if (empty($errors)) {
                $user = $this->createUser();
                $this->isSubmitted = true;
                
                $this->emit('user-registered', [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email
                ]);
            }
        } catch (\Exception $e) {
            $this->submitError = 'Registration failed. Please try again.';
            $this->log('error', 'User registration failed', [
                'error' => $e->getMessage(),
                'username' => $this->username,
                'email' => $this->email
            ]);
        } finally {
            $this->isSubmitting = false;
        }
    }
    
    private function createUser(): object
    {
        // Create user in database
        return (object) [
            'id' => 123,
            'username' => $this->username,
            'email' => $this->email
        ];
    }
}
```

### Multi-Step Form Submission

```php
class MultiStepFormComponent extends SparkComponent
{
    public int $currentStep = 1;
    public int $totalSteps = 3;
    
    // Step 1: Personal Info
    public string $firstName = '';
    public string $lastName = '';
    public string $email = '';
    
    // Step 2: Address Info
    public string $address = '';
    public string $city = '';
    public string $zipCode = '';
    
    // Step 3: Preferences
    public bool $newsletter = false;
    public string $preferredContact = 'email';
    public array $interests = [];
    
    public bool $isSubmitting = false;
    
    protected array $stepRules = [
        1 => [
            'firstName' => 'required|min:2',
            'lastName' => 'required|min:2',
            'email' => 'required|email'
        ],
        2 => [
            'address' => 'required',
            'city' => 'required',
            'zipCode' => 'required|regex:/^\d{5}$/'
        ],
        3 => [
            'preferredContact' => 'required|in:email,phone,mail'
        ]
    ];
    
    public function nextStep(): void
    {
        if ($this->validateCurrentStep()) {
            if ($this->currentStep < $this->totalSteps) {
                $this->currentStep++;
            }
        }
    }
    
    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }
    
    public function submitForm(): void
    {
        if ($this->validateAllSteps()) {
            $this->isSubmitting = true;
            
            try {
                $this->processFormSubmission();
                $this->emit('multi-step-form-completed', $this->getAllFormData());
            } catch (\Exception $e) {
                $this->log('error', 'Multi-step form submission failed', [
                    'error' => $e->getMessage(),
                    'step' => $this->currentStep
                ]);
            } finally {
                $this->isSubmitting = false;
            }
        }
    }
    
    private function validateCurrentStep(): bool
    {
        $rules = $this->stepRules[$this->currentStep] ?? [];
        $errors = $this->validateWith($rules);
        return empty($errors);
    }
    
    private function validateAllSteps(): bool
    {
        $allRules = [];
        foreach ($this->stepRules as $stepRules) {
            $allRules = array_merge($allRules, $stepRules);
        }
        
        $errors = $this->validateWith($allRules);
        return empty($errors);
    }
    
    private function getAllFormData(): array
    {
        return [
            'personal' => [
                'firstName' => $this->firstName,
                'lastName' => $this->lastName,
                'email' => $this->email
            ],
            'address' => [
                'address' => $this->address,
                'city' => $this->city,
                'zipCode' => $this->zipCode
            ],
            'preferences' => [
                'newsletter' => $this->newsletter,
                'preferredContact' => $this->preferredContact,
                'interests' => $this->interests
            ]
        ];
    }
}
```

## Input Types

### Custom Input Components

```php
class CustomInputsComponent extends SparkComponent
{
    public string $tagInput = '';
    public array $tags = [];
    public float $rating = 0.0;
    public string $richText = '';
    
    public function addTag(): void
    {
        $tag = trim($this->tagInput);
        if (!empty($tag) && !in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
            $this->tagInput = '';
        }
    }
    
    public function removeTag(string $tag): void
    {
        $this->tags = array_values(array_filter($this->tags, fn($t) => $t !== $tag));
    }
    
    public function setRating(float $rating): void
    {
        $this->rating = max(0, min(5, $rating));
    }
}
```

```html
<!-- Custom input templates -->
<div class="custom-inputs">
    <!-- Tag Input -->
    <div class="form-group">
        <label>Tags</label>
        <div class="tag-input">
            <div class="tags">
                @foreach($tags as $tag)
                    <span class="tag">
                        {{ $tag }}
                        <button spark:click="removeTag('{{ $tag }}')" class="tag-remove">×</button>
                    </span>
                @endforeach
            </div>
            <input type="text" 
                   spark:model="tagInput"
                   spark:on-keydown="if(event.key==='Enter'){event.preventDefault();addTag();}"
                   placeholder="Add a tag...">
            <button spark:click="addTag" class="btn-sm">Add</button>
        </div>
    </div>
    
    <!-- Star Rating -->
    <div class="form-group">
        <label>Rating</label>
        <div class="star-rating">
            @for($i = 1; $i <= 5; $i++)
                <button spark:click="setRating({{ $i }})"
                        class="star {{ $rating >= $i ? 'active' : '' }}">
                    ★
                </button>
            @endfor
            <span class="rating-value">{{ $rating }}/5</span>
        </div>
    </div>
</div>
```

### Date and Time Inputs

```php
class DateTimeFormComponent extends SparkComponent
{
    public string $startDate = '';
    public string $endDate = '';
    public string $startTime = '';
    public string $endTime = '';
    public string $timezone = 'UTC';
    
    public function calculateDuration(): string
    {
        if (empty($this->startDate) || empty($this->endDate)) {
            return '';
        }
        
        try {
            $start = new \DateTime($this->startDate . ' ' . ($this->startTime ?: '00:00'));
            $end = new \DateTime($this->endDate . ' ' . ($this->endTime ?: '23:59'));
            
            $interval = $start->diff($end);
            return $interval->format('%d days, %h hours, %i minutes');
        } catch (\Exception $e) {
            return 'Invalid date range';
        }
    }
    
    protected function updated(string $property, mixed $value): void
    {
        if (in_array($property, ['startDate', 'endDate', 'startTime', 'endTime'])) {
            // Automatically calculate duration when dates change
            $this->duration = $this->calculateDuration();
        }
    }
}
```

## File Uploads

### File Upload Component

```php
class FileUploadComponent extends SparkComponent
{
    public array $uploadedFiles = [];
    public bool $isUploading = false;
    public ?string $uploadError = null;
    public int $maxFileSize = 5242880; // 5MB
    public array $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    
    public function handleFileUpload(Request $request): void
    {
        $this->isUploading = true;
        $this->uploadError = null;
        
        try {
            $uploadedFile = $request->files->get('file');
            
            if ($this->validateFile($uploadedFile)) {
                $filePath = $this->saveFile($uploadedFile);
                
                $this->uploadedFiles[] = [
                    'id' => uniqid(),
                    'name' => $uploadedFile->getClientOriginalName(),
                    'size' => $uploadedFile->getSize(),
                    'type' => $uploadedFile->getMimeType(),
                    'path' => $filePath,
                    'uploaded_at' => time()
                ];
                
                $this->emit('file-uploaded', [
                    'file_name' => $uploadedFile->getClientOriginalName(),
                    'file_size' => $uploadedFile->getSize()
                ]);
            }
        } catch (\Exception $e) {
            $this->uploadError = $e->getMessage();
        } finally {
            $this->isUploading = false;
        }
    }
    
    public function removeFile(string $fileId): void
    {
        $this->uploadedFiles = array_filter($this->uploadedFiles, fn($file) => $file['id'] !== $fileId);
    }
    
    private function validateFile($file): bool
    {
        if (!$file || !$file->isValid()) {
            throw new \Exception('Invalid file upload');
        }
        
        if ($file->getSize() > $this->maxFileSize) {
            throw new \Exception('File size exceeds limit');
        }
        
        if (!in_array($file->getMimeType(), $this->allowedTypes)) {
            throw new \Exception('File type not allowed');
        }
        
        return true;
    }
    
    private function saveFile($file): string
    {
        $filename = uniqid() . '.' . $file->getClientOriginalExtension();
        $directory = 'uploads/' . date('Y/m/d');
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $file->move($directory, $filename);
        return $directory . '/' . $filename;
    }
}
```

```html
<!-- File upload template -->
<div class="file-upload">
    <div class="upload-area" 
         ondrop="handleFileDrop(event)" 
         ondragover="event.preventDefault()"
         ondragenter="event.preventDefault()">
        
        <input type="file" 
               id="file-input"
               multiple
               accept="image/*,.pdf"
               onchange="handleFileSelect(event)"
               style="display: none;">
        
        <div class="upload-prompt">
            <p>Drag files here or <button onclick="document.getElementById('file-input').click()">browse</button></p>
            <small>Supported: JPEG, PNG, GIF, PDF (max 5MB each)</small>
        </div>
    </div>
    
    @if($uploadError)
        <div class="error-message">{{ $uploadError }}</div>
    @endif
    
    @if($isUploading)
        <div class="uploading">Uploading file...</div>
    @endif
    
    @if(!empty($uploadedFiles))
        <div class="uploaded-files">
            <h4>Uploaded Files</h4>
            @foreach($uploadedFiles as $file)
                <div class="file-item">
                    <span class="file-name">{{ $file['name'] }}</span>
                    <span class="file-size">{{ number_format($file['size'] / 1024, 1) }} KB</span>
                    <button spark:click="removeFile('{{ $file['id'] }}')" class="remove-btn">Remove</button>
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
function handleFileDrop(event) {
    event.preventDefault();
    const files = event.dataTransfer.files;
    for (let file of files) {
        uploadFile(file);
    }
}

function handleFileSelect(event) {
    const files = event.target.files;
    for (let file of files) {
        uploadFile(file);
    }
}

function uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    
    // Use Spark's AJAX system to upload file
    // This would integrate with your component's handleFileUpload method
}
</script>
```

## Form Validation

### Real-time Validation

```php
class ValidatedFormComponent extends SparkComponent
{
    public string $username = '';
    public string $email = '';
    public string $password = '';
    public string $confirmPassword = '';
    
    public array $fieldErrors = [];
    public array $fieldValidStatus = [];
    
    protected array $rules = [
        'username' => 'required|min:3|max:20|alpha_dash',
        'email' => 'required|email',
        'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
        'confirmPassword' => 'required|same:password'
    ];
    
    protected function updated(string $property, mixed $value): void
    {
        // Validate field in real-time
        $this->validateField($property);
    }
    
    private function validateField(string $field): void
    {
        if (!isset($this->rules[$field])) {
            return;
        }
        
        $fieldRules = [$field => $this->rules[$field]];
        $errors = $this->validateWith($fieldRules);
        
        if (empty($errors)) {
            unset($this->fieldErrors[$field]);
            $this->fieldValidStatus[$field] = true;
        } else {
            $this->fieldErrors[$field] = $errors[$field];
            $this->fieldValidStatus[$field] = false;
        }
    }
    
    public function getFieldStatus(string $field): string
    {
        if (!isset($this->fieldValidStatus[$field])) {
            return '';
        }
        
        return $this->fieldValidStatus[$field] ? 'valid' : 'invalid';
    }
    
    public function getFieldError(string $field): string
    {
        return $this->fieldErrors[$field][0] ?? '';
    }
}
```

```html
<!-- Real-time validation template -->
<form spark:submit="submit" class="validated-form">
    <div class="form-group">
        <label>Username</label>
        <input type="text" 
               spark:model="username"
               spark:on-blur="render"
               class="form-input {{ $this->getFieldStatus('username') }}"
               placeholder="Choose a username">
        @if($this->getFieldError('username'))
            <div class="field-error">{{ $this->getFieldError('username') }}</div>
        @endif
        @if($this->getFieldStatus('username') === 'valid')
            <div class="field-success">✓ Username is available</div>
        @endif
    </div>
    
    <div class="form-group">
        <label>Email</label>
        <input type="email" 
               spark:model="email"
               spark:on-blur="render"
               class="form-input {{ $this->getFieldStatus('email') }}"
               placeholder="your@email.com">
        @if($this->getFieldError('email'))
            <div class="field-error">{{ $this->getFieldError('email') }}</div>
        @endif
    </div>
    
    <div class="form-group">
        <label>Password</label>
        <input type="password" 
               spark:model="password"
               spark:on-blur="render"
               class="form-input {{ $this->getFieldStatus('password') }}"
               placeholder="Choose a strong password">
        @if($this->getFieldError('password'))
            <div class="field-error">{{ $this->getFieldError('password') }}</div>
        @endif
        <div class="password-requirements">
            <small>Must be at least 8 characters with uppercase, lowercase, and numbers</small>
        </div>
    </div>
</form>
```

## Dynamic Forms

### Form Builder Component

```php
class DynamicFormComponent extends SparkComponent
{
    public array $formSchema = [];
    public array $formData = [];
    public array $fieldErrors = [];
    
    protected function mount(): void
    {
        $this->formSchema = [
            [
                'name' => 'first_name',
                'type' => 'text',
                'label' => 'First Name',
                'required' => true,
                'validation' => 'required|min:2'
            ],
            [
                'name' => 'email',
                'type' => 'email',
                'label' => 'Email Address',
                'required' => true,
                'validation' => 'required|email'
            ],
            [
                'name' => 'age',
                'type' => 'number',
                'label' => 'Age',
                'required' => false,
                'validation' => 'numeric|min:18|max:120'
            ],
            [
                'name' => 'country',
                'type' => 'select',
                'label' => 'Country',
                'required' => true,
                'options' => [
                    'us' => 'United States',
                    'ca' => 'Canada',
                    'uk' => 'United Kingdom',
                    'de' => 'Germany'
                ],
                'validation' => 'required'
            ]
        ];
        
        // Initialize form data
        foreach ($this->formSchema as $field) {
            $this->formData[$field['name']] = '';
        }
    }
    
    public function updateField(string $fieldName, mixed $value): void
    {
        $this->formData[$fieldName] = $value;
        $this->validateField($fieldName);
    }
    
    public function addField(array $fieldConfig): void
    {
        $this->formSchema[] = $fieldConfig;
        $this->formData[$fieldConfig['name']] = '';
    }
    
    public function removeField(string $fieldName): void
    {
        $this->formSchema = array_filter($this->formSchema, fn($field) => $field['name'] !== $fieldName);
        unset($this->formData[$fieldName]);
        unset($this->fieldErrors[$fieldName]);
    }
    
    private function validateField(string $fieldName): void
    {
        $field = $this->getFieldByName($fieldName);
        if (!$field || empty($field['validation'])) {
            return;
        }
        
        $rules = [$fieldName => $field['validation']];
        $errors = $this->validateWith($rules);
        
        if (empty($errors)) {
            unset($this->fieldErrors[$fieldName]);
        } else {
            $this->fieldErrors[$fieldName] = $errors[$fieldName];
        }
    }
    
    private function getFieldByName(string $name): ?array
    {
        foreach ($this->formSchema as $field) {
            if ($field['name'] === $name) {
                return $field;
            }
        }
        return null;
    }
}
```

## Advanced Patterns

### Conditional Field Display

```php
class ConditionalFormComponent extends SparkComponent
{
    public string $userType = '';
    public string $companyName = '';
    public string $vatNumber = '';
    public bool $needsInvoice = false;
    public string $invoiceEmail = '';
    
    public function getVisibleFields(): array
    {
        $fields = ['userType'];
        
        if ($this->userType === 'business') {
            $fields[] = 'companyName';
            $fields[] = 'vatNumber';
        }
        
        if ($this->userType === 'individual' || $this->userType === 'business') {
            $fields[] = 'needsInvoice';
        }
        
        if ($this->needsInvoice) {
            $fields[] = 'invoiceEmail';
        }
        
        return $fields;
    }
    
    protected function updated(string $property, mixed $value): void
    {
        // Clear dependent fields when parent field changes
        if ($property === 'userType') {
            $this->companyName = '';
            $this->vatNumber = '';
            $this->needsInvoice = false;
            $this->invoiceEmail = '';
        } elseif ($property === 'needsInvoice' && !$value) {
            $this->invoiceEmail = '';
        }
    }
}
```

### Form with Repeatable Sections

```php
class RepeatableFormComponent extends SparkComponent
{
    public array $contacts = [];
    
    protected function mount(): void
    {
        // Start with one contact
        $this->addContact();
    }
    
    public function addContact(): void
    {
        $this->contacts[] = [
            'id' => uniqid(),
            'name' => '',
            'email' => '',
            'phone' => '',
            'role' => ''
        ];
    }
    
    public function removeContact(string $contactId): void
    {
        $this->contacts = array_filter($this->contacts, fn($contact) => $contact['id'] !== $contactId);
        $this->contacts = array_values($this->contacts); // Re-index array
    }
    
    public function updateContact(string $contactId, string $field, mixed $value): void
    {
        foreach ($this->contacts as &$contact) {
            if ($contact['id'] === $contactId) {
                $contact[$field] = $value;
                break;
            }
        }
    }
}
```

```html
<!-- Repeatable form sections -->
<div class="repeatable-form">
    <h3>Contacts</h3>
    
    @foreach($contacts as $index => $contact)
        <div class="contact-section" data-contact-id="{{ $contact['id'] }}">
            <h4>Contact {{ $index + 1 }}</h4>
            
            <div class="form-row">
                <input type="text" 
                       value="{{ $contact['name'] }}"
                       spark:on-blur="updateContact('{{ $contact['id'] }}', 'name', event.target.value)"
                       placeholder="Name">
                
                <input type="email" 
                       value="{{ $contact['email'] }}"
                       spark:on-blur="updateContact('{{ $contact['id'] }}', 'email', event.target.value)"
                       placeholder="Email">
            </div>
            
            <button spark:click="removeContact('{{ $contact['id'] }}')" 
                    class="btn-danger btn-sm">
                Remove Contact
            </button>
        </div>
    @endforeach
    
    <button spark:click="addContact" class="btn-secondary">
        Add Another Contact
    </button>
</div>
```

Form handling in Spark provides powerful, reactive form experiences with minimal JavaScript while maintaining full server-side control over validation and processing.