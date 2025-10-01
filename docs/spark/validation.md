# Validation

This guide covers comprehensive validation in Spark components, including built-in rules, custom validation, real-time validation, and error handling.

## Table of Contents

1. [Validation Basics](#validation-basics)
2. [Built-in Validation Rules](#built-in-validation-rules)
3. [Custom Validation Rules](#custom-validation-rules)
4. [Real-time Validation](#real-time-validation)
5. [Error Handling](#error-handling)
6. [Conditional Validation](#conditional-validation)
7. [Advanced Validation Patterns](#advanced-validation-patterns)

## Validation Basics

### Simple Validation Setup

```php
<?php
namespace App\SparkComponents;

use Elementary\Spark\SparkComponent;

class UserFormComponent extends SparkComponent
{
    public string $name = '';
    public string $email = '';
    public int $age = 0;
    public string $website = '';
    
    // Define validation rules
    protected array $rules = [
        'name' => 'required|min:2|max:50',
        'email' => 'required|email|unique:users,email',
        'age' => 'required|numeric|min:18|max:120',
        'website' => 'url'
    ];
    
    public function save(): void
    {
        // Validate all fields
        $errors = $this->validate();
        
        if (empty($errors)) {
            // Validation passed - process the data
            $this->processUserData();
            $this->emit('user-saved', $this->getPublicProperties());
        } else {
            // Validation failed - errors are automatically displayed
            $this->emit('validation-failed', ['errors' => $errors]);
        }
    }
    
    private function processUserData(): void
    {
        // Save user to database, send emails, etc.
    }
}
```

### Template with Error Display

```html
<!-- templates/spark/userform.cigg -->
<form spark:submit="save" class="user-form">
    <div class="form-group">
        <label for="name">Name *</label>
        <input type="text" 
               id="name"
               spark:model="name"
               class="form-input {{ $errors?->has('name') ? 'error' : '' }}">
        @if($errors?->has('name'))
            <div class="error-message">{{ $errors->first('name') }}</div>
        @endif
    </div>
    
    <div class="form-group">
        <label for="email">Email *</label>
        <input type="email" 
               id="email"
               spark:model="email"
               class="form-input {{ $errors?->has('email') ? 'error' : '' }}">
        @if($errors?->has('email'))
            <div class="error-message">{{ $errors->first('email') }}</div>
        @endif
    </div>
    
    <div class="form-group">
        <label for="age">Age *</label>
        <input type="number" 
               id="age"
               spark:model="age"
               class="form-input {{ $errors?->has('age') ? 'error' : '' }}">
        @if($errors?->has('age'))
            <div class="error-message">{{ $errors->first('age') }}</div>
        @endif
    </div>
    
    <div class="form-group">
        <label for="website">Website</label>
        <input type="url" 
               id="website"
               spark:model="website"
               class="form-input {{ $errors?->has('website') ? 'error' : '' }}">
        @if($errors?->has('website'))
            <div class="error-message">{{ $errors->first('website') }}</div>
        @endif
    </div>
    
    <button type="submit" class="btn-primary">Save User</button>
</form>
```

## Built-in Validation Rules

### Basic Rules

```php
class ValidationRulesComponent extends SparkComponent
{
    // Required fields
    public string $requiredField = '';
    
    // String validation
    public string $minString = '';
    public string $maxString = '';
    public string $exactString = '';
    
    // Numeric validation
    public int $minNumber = 0;
    public int $maxNumber = 0;
    public float $betweenNumber = 0.0;
    
    // Format validation
    public string $emailField = '';
    public string $urlField = '';
    public string $alphaField = '';
    public string $alphaNumField = '';
    public string $regexField = '';
    
    protected array $rules = [
        // Required validation
        'requiredField' => 'required',
        
        // String length validation
        'minString' => 'min:5',
        'maxString' => 'max:20',
        'exactString' => 'size:10',
        
        // Numeric validation
        'minNumber' => 'numeric|min:18',
        'maxNumber' => 'numeric|max:65',
        'betweenNumber' => 'numeric|between:0,100',
        
        // Format validation
        'emailField' => 'email',
        'urlField' => 'url',
        'alphaField' => 'alpha',
        'alphaNumField' => 'alpha_num',
        'regexField' => 'regex:/^[A-Z]{2,4}$/',
    ];
}
```

### Advanced Rules

```php
class AdvancedValidationComponent extends SparkComponent
{
    public string $username = '';
    public string $password = '';
    public string $confirmPassword = '';
    public string $birthDate = '';
    public array $selectedItems = [];
    public string $userRole = '';
    
    protected array $rules = [
        // Unique validation (database check)
        'username' => 'required|min:3|unique:users,username',
        
        // Confirmation validation
        'password' => 'required|min:8|confirmed',
        'confirmPassword' => 'required',
        
        // Date validation
        'birthDate' => 'required|date|before:today',
        
        // Array validation
        'selectedItems' => 'required|array|min:1|max:5',
        'selectedItems.*' => 'string|distinct',
        
        // Conditional validation
        'userRole' => 'required|in:admin,user,moderator',
    ];
    
    protected array $messages = [
        'username.unique' => 'This username is already taken.',
        'password.confirmed' => 'Password confirmation does not match.',
        'birthDate.before' => 'You must be born before today.',
        'selectedItems.min' => 'Please select at least one item.',
        'selectedItems.max' => 'You can select a maximum of 5 items.',
    ];
}
```

### File Validation

```php
class FileValidationComponent extends SparkComponent
{
    public $profileImage = null;
    public $documents = [];
    
    protected array $rules = [
        'profileImage' => 'required|file|image|max:2048', // Max 2MB
        'documents.*' => 'file|mimes:pdf,doc,docx|max:10240', // Max 10MB each
    ];
    
    public function handleFileUpload(Request $request): void
    {
        // Validate uploaded files
        $errors = $this->validateFiles([
            'profileImage' => $request->files->get('profileImage'),
            'documents' => $request->files->get('documents', [])
        ]);
        
        if (empty($errors)) {
            $this->processFileUploads($request);
        }
    }
    
    private function validateFiles(array $files): array
    {
        $validator = new Validator($files, $this->rules);
        return $validator->fails() ? $validator->errors() : [];
    }
}
```

## Custom Validation Rules

### Creating Custom Rules

```php
class CustomValidationComponent extends SparkComponent
{
    public string $phoneNumber = '';
    public string $socialSecurity = '';
    public string $creditCard = '';
    public string $strongPassword = '';
    
    protected array $rules = [
        'phoneNumber' => 'required|phone',
        'socialSecurity' => 'required|ssn',
        'creditCard' => 'required|credit_card',
        'strongPassword' => 'required|strong_password',
    ];
    
    public function validate(): array
    {
        $validator = new \Elementary\Validation\Validator($this->getPublicProperties(), $this->rules);
        
        // Add custom validation rules
        $validator->extend('phone', function($field, $value, $parameters) {
            return preg_match('/^\+?[1-9]\d{1,14}$/', $value);
        }, 'The :field must be a valid phone number.');
        
        $validator->extend('ssn', function($field, $value, $parameters) {
            return preg_match('/^\d{3}-\d{2}-\d{4}$/', $value);
        }, 'The :field must be in format XXX-XX-XXXX.');
        
        $validator->extend('credit_card', function($field, $value, $parameters) {
            return $this->validateCreditCard($value);
        }, 'The :field must be a valid credit card number.');
        
        $validator->extend('strong_password', function($field, $value, $parameters) {
            return $this->validateStrongPassword($value);
        }, 'The :field must contain uppercase, lowercase, numbers, and symbols.');
        
        return $validator->fails() ? $validator->errors() : [];
    }
    
    private function validateCreditCard(string $number): bool
    {
        // Luhn algorithm validation
        $number = preg_replace('/\D/', '', $number);
        
        if (strlen($number) < 13 || strlen($number) > 19) {
            return false;
        }
        
        $sum = 0;
        $alternate = false;
        
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = intval($number[$i]);
            
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = ($digit % 10) + 1;
                }
            }
            
            $sum += $digit;
            $alternate = !$alternate;
        }
        
        return ($sum % 10) === 0;
    }
    
    private function validateStrongPassword(string $password): bool
    {
        return strlen($password) >= 8 &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[0-9]/', $password) &&
               preg_match('/[^a-zA-Z0-9]/', $password);
    }
}
```

### Business Logic Validation

```php
class BusinessValidationComponent extends SparkComponent
{
    public string $productCode = '';
    public int $quantity = 0;
    public string $discountCode = '';
    public float $orderTotal = 0.0;
    
    protected array $rules = [
        'productCode' => 'required|exists:products,code',
        'quantity' => 'required|numeric|min:1',
        'discountCode' => 'discount_code',
        'orderTotal' => 'required|numeric|min:0.01',
    ];
    
    public function validate(): array
    {
        $validator = new \Elementary\Validation\Validator($this->getPublicProperties(), $this->rules);
        
        // Custom business rule validation
        $validator->extend('discount_code', function($field, $value, $parameters) {
            if (empty($value)) {
                return true; // Optional field
            }
            
            return $this->validateDiscountCode($value);
        }, 'The discount code is invalid or expired.');
        
        // Add after validation for complex business rules
        $validator->after(function($validator) {
            // Check inventory
            if (!$this->checkInventory($this->productCode, $this->quantity)) {
                $validator->errors()->add('quantity', 'Insufficient inventory available.');
            }
            
            // Validate order total
            $calculatedTotal = $this->calculateOrderTotal();
            if (abs($this->orderTotal - $calculatedTotal) > 0.01) {
                $validator->errors()->add('orderTotal', 'Order total does not match calculated amount.');
            }
            
            // Business hours validation
            if (!$this->isWithinBusinessHours()) {
                $validator->errors()->add('general', 'Orders can only be placed during business hours.');
            }
        });
        
        return $validator->fails() ? $validator->errors() : [];
    }
    
    private function validateDiscountCode(string $code): bool
    {
        // Check if discount code exists and is valid
        $discount = $this->getDiscountByCode($code);
        
        return $discount && 
               $discount->isActive() && 
               !$discount->isExpired() && 
               $discount->isApplicableToOrder($this->getOrderData());
    }
    
    private function checkInventory(string $productCode, int $quantity): bool
    {
        $product = $this->getProductByCode($productCode);
        return $product && $product->getStock() >= $quantity;
    }
    
    private function calculateOrderTotal(): float
    {
        // Calculate total based on products, taxes, discounts, etc.
        return 0.0; // Simplified
    }
    
    private function isWithinBusinessHours(): bool
    {
        $currentHour = (int)date('H');
        return $currentHour >= 9 && $currentHour < 17; // 9 AM to 5 PM
    }
}
```

## Real-time Validation

### Field-Level Validation

```php
class RealTimeValidationComponent extends SparkComponent
{
    public string $username = '';
    public string $email = '';
    public string $password = '';
    
    public array $fieldStatus = [];
    public array $fieldErrors = [];
    
    protected array $rules = [
        'username' => 'required|min:3|max:20|alpha_dash|unique:users',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:8|strong_password',
    ];
    
    protected function updated(string $property, mixed $value): void
    {
        // Validate field in real-time when it changes
        $this->validateField($property);
    }
    
    public function validateField(string $field): void
    {
        if (!isset($this->rules[$field])) {
            return;
        }
        
        // Create validator for single field
        $validator = new \Elementary\Validation\Validator(
            [$field => $this->$field],
            [$field => $this->rules[$field]]
        );
        
        if ($validator->fails()) {
            $this->fieldErrors[$field] = $validator->errors()[$field] ?? [];
            $this->fieldStatus[$field] = 'invalid';
        } else {
            unset($this->fieldErrors[$field]);
            $this->fieldStatus[$field] = 'valid';
        }
    }
    
    public function getFieldClass(string $field): string
    {
        return match($this->fieldStatus[$field] ?? '') {
            'valid' => 'field-valid',
            'invalid' => 'field-invalid',
            default => ''
        };
    }
    
    public function getFieldError(string $field): string
    {
        return $this->fieldErrors[$field][0] ?? '';
    }
    
    public function hasFieldError(string $field): bool
    {
        return !empty($this->fieldErrors[$field]);
    }
}
```

### Progressive Validation

```php
class ProgressiveValidationComponent extends SparkComponent
{
    public string $email = '';
    public string $password = '';
    public string $confirmPassword = '';
    
    public array $validationSteps = [
        'email' => false,
        'password' => false,
        'confirmPassword' => false
    ];
    
    protected function updated(string $property, mixed $value): void
    {
        switch ($property) {
            case 'email':
                $this->validateEmail();
                break;
            case 'password':
                $this->validatePassword();
                // Re-validate confirm password if it exists
                if (!empty($this->confirmPassword)) {
                    $this->validateConfirmPassword();
                }
                break;
            case 'confirmPassword':
                $this->validateConfirmPassword();
                break;
        }
    }
    
    private function validateEmail(): void
    {
        $this->validationSteps['email'] = !empty($this->email) && 
                                          filter_var($this->email, FILTER_VALIDATE_EMAIL) &&
                                          $this->isEmailUnique($this->email);
    }
    
    private function validatePassword(): void
    {
        $this->validationSteps['password'] = strlen($this->password) >= 8 &&
                                           preg_match('/[A-Z]/', $this->password) &&
                                           preg_match('/[a-z]/', $this->password) &&
                                           preg_match('/[0-9]/', $this->password);
    }
    
    private function validateConfirmPassword(): void
    {
        $this->validationSteps['confirmPassword'] = !empty($this->confirmPassword) &&
                                                   $this->password === $this->confirmPassword;
    }
    
    public function getOverallProgress(): int
    {
        $completed = array_sum($this->validationSteps);
        $total = count($this->validationSteps);
        return $total > 0 ? (int)(($completed / $total) * 100) : 0;
    }
    
    public function canSubmit(): bool
    {
        return array_sum($this->validationSteps) === count($this->validationSteps);
    }
}
```

### Debounced Validation

```php
class DebouncedValidationComponent extends SparkComponent
{
    public string $searchTerm = '';
    public string $usernameCheck = '';
    
    public bool $isValidatingSearch = false;
    public bool $isValidatingUsername = false;
    public array $searchErrors = [];
    public array $usernameErrors = [];
    
    private array $debounceTimers = [];
    
    protected function updated(string $property, mixed $value): void
    {
        switch ($property) {
            case 'searchTerm':
                $this->debounceValidation('search', 'validateSearch', 500);
                break;
            case 'usernameCheck':
                $this->debounceValidation('username', 'validateUsername', 1000);
                break;
        }
    }
    
    private function debounceValidation(string $key, string $method, int $delay): void
    {
        // Clear existing timer
        if (isset($this->debounceTimers[$key])) {
            clearTimeout($this->debounceTimers[$key]);
        }
        
        // Set loading state
        $this->{"isValidating" . ucfirst($key)} = true;
        
        // Set new timer
        $this->debounceTimers[$key] = setTimeout(function() use ($method, $key) {
            $this->$method();
            $this->{"isValidating" . ucfirst($key)} = false;
        }, $delay);
    }
    
    private function validateSearch(): void
    {
        if (strlen($this->searchTerm) < 3) {
            $this->searchErrors = ['Search term must be at least 3 characters'];
            return;
        }
        
        // Simulate API validation
        $isValid = $this->performSearchValidation($this->searchTerm);
        $this->searchErrors = $isValid ? [] : ['No results found for this search term'];
    }
    
    private function validateUsername(): void
    {
        if (strlen($this->usernameCheck) < 3) {
            $this->usernameErrors = ['Username must be at least 3 characters'];
            return;
        }
        
        // Check username availability
        $isAvailable = $this->checkUsernameAvailability($this->usernameCheck);
        $this->usernameErrors = $isAvailable ? [] : ['Username is already taken'];
    }
}
```

## Error Handling

### Error Display Strategies

```php
class ErrorDisplayComponent extends SparkComponent
{
    public string $formData = '';
    public array $errors = [];
    public array $warnings = [];
    public array $infos = [];
    
    public function processForm(): void
    {
        $validationResult = $this->validateWithDetails();
        
        $this->errors = $validationResult['errors'] ?? [];
        $this->warnings = $validationResult['warnings'] ?? [];
        $this->infos = $validationResult['infos'] ?? [];
    }
    
    private function validateWithDetails(): array
    {
        $result = [
            'errors' => [],
            'warnings' => [],
            'infos' => []
        ];
        
        // Critical errors (prevent submission)
        if (empty($this->formData)) {
            $result['errors'][] = 'Form data is required';
        }
        
        // Warnings (allow submission but show warning)
        if (strlen($this->formData) < 10) {
            $result['warnings'][] = 'Consider providing more detailed information';
        }
        
        // Info messages (helpful tips)
        if (strpos($this->formData, '@') !== false) {
            $result['infos'][] = 'We detected an email address in your input';
        }
        
        return $result;
    }
    
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
    
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }
    
    public function hasInfos(): bool
    {
        return !empty($this->infos);
    }
}
```

### Error Recovery

```php
class ErrorRecoveryComponent extends SparkComponent
{
    public string $input = '';
    public array $suggestions = [];
    public array $errors = [];
    
    protected array $rules = [
        'input' => 'required|email'
    ];
    
    public function validate(): array
    {
        $validator = new \Elementary\Validation\Validator($this->getPublicProperties(), $this->rules);
        
        if ($validator->fails()) {
            $errors = $validator->errors();
            
            // Generate suggestions for common errors
            $this->suggestions = $this->generateSuggestions($errors);
            
            return $errors;
        }
        
        $this->suggestions = [];
        return [];
    }
    
    private function generateSuggestions(array $errors): array
    {
        $suggestions = [];
        
        foreach ($errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                if (strpos($error, 'email') !== false) {
                    $suggestions[] = $this->suggestEmailCorrection($this->$field);
                }
            }
        }
        
        return array_filter($suggestions);
    }
    
    private function suggestEmailCorrection(string $input): ?string
    {
        // Common email typos and corrections
        $commonDomains = [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
            'aol.com', 'icloud.com', 'live.com'
        ];
        
        $typos = [
            'gmial.com' => 'gmail.com',
            'gmai.com' => 'gmail.com',
            'yaho.com' => 'yahoo.com',
            'hotmai.com' => 'hotmail.com'
        ];
        
        foreach ($typos as $typo => $correction) {
            if (strpos($input, $typo) !== false) {
                return "Did you mean: " . str_replace($typo, $correction, $input) . "?";
            }
        }
        
        // Check for missing @ symbol
        if (strpos($input, '@') === false && strpos($input, '.') !== false) {
            $parts = explode('.', $input);
            if (count($parts) >= 2) {
                return "Did you mean: " . $parts[0] . "@" . implode('.', array_slice($parts, 1)) . "?";
            }
        }
        
        return null;
    }
    
    public function applySuggestion(string $suggestion): void
    {
        // Extract the suggested value from the suggestion text
        if (preg_match('/Did you mean: (.+)\?/', $suggestion, $matches)) {
            $this->input = $matches[1];
            $this->suggestions = [];
        }
    }
}
```

## Conditional Validation

### Rule Dependencies

```php
class ConditionalValidationComponent extends SparkComponent
{
    public string $accountType = '';
    public string $companyName = '';
    public string $taxId = '';
    public bool $isNonProfit = false;
    public string $nonProfitId = '';
    public string $businessLicense = '';
    
    public function validate(): array
    {
        $rules = [
            'accountType' => 'required|in:personal,business,nonprofit'
        ];
        
        // Add conditional rules based on account type
        switch ($this->accountType) {
            case 'business':
                $rules['companyName'] = 'required|min:2|max:100';
                $rules['taxId'] = 'required|regex:/^\d{2}-\d{7}$/';
                $rules['businessLicense'] = 'required';
                break;
                
            case 'nonprofit':
                $rules['companyName'] = 'required|min:2|max:100';
                $rules['nonProfitId'] = 'required|regex:/^\d{2}-\d{7}$/';
                break;
                
            case 'personal':
                // No additional rules for personal accounts
                break;
        }
        
        // Additional conditional validation
        if ($this->accountType === 'nonprofit' && $this->isNonProfit) {
            $rules['nonProfitId'] = 'required|verified_nonprofit';
        }
        
        $validator = new \Elementary\Validation\Validator($this->getPublicProperties(), $rules);
        
        // Add custom conditional validation
        $validator->sometimes('taxId', 'required|unique:businesses,tax_id', function($input) {
            return $input->accountType === 'business' && !empty($input->companyName);
        });
        
        return $validator->fails() ? $validator->errors() : [];
    }
    
    public function getRequiredFields(): array
    {
        $required = ['accountType'];
        
        switch ($this->accountType) {
            case 'business':
                $required = array_merge($required, ['companyName', 'taxId', 'businessLicense']);
                break;
            case 'nonprofit':
                $required = array_merge($required, ['companyName', 'nonProfitId']);
                break;
        }
        
        return $required;
    }
}
```

### Multi-Step Validation

```php
class MultiStepValidationComponent extends SparkComponent
{
    public int $currentStep = 1;
    public int $totalSteps = 3;
    
    // Step data
    public array $stepData = [
        1 => ['name' => '', 'email' => ''],
        2 => ['address' => '', 'city' => '', 'zip' => ''],
        3 => ['cardNumber' => '', 'expiryDate' => '', 'cvv' => '']
    ];
    
    protected array $stepRules = [
        1 => [
            'stepData.1.name' => 'required|min:2',
            'stepData.1.email' => 'required|email'
        ],
        2 => [
            'stepData.2.address' => 'required',
            'stepData.2.city' => 'required',
            'stepData.2.zip' => 'required|regex:/^\d{5}$/'
        ],
        3 => [
            'stepData.3.cardNumber' => 'required|credit_card',
            'stepData.3.expiryDate' => 'required|date_format:m/y|after:today',
            'stepData.3.cvv' => 'required|digits:3'
        ]
    ];
    
    public function validateCurrentStep(): bool
    {
        $rules = $this->stepRules[$this->currentStep] ?? [];
        
        if (empty($rules)) {
            return true;
        }
        
        $validator = new \Elementary\Validation\Validator($this->getPublicProperties(), $rules);
        return !$validator->fails();
    }
    
    public function validateAllSteps(): array
    {
        $allErrors = [];
        
        for ($step = 1; $step <= $this->totalSteps; $step++) {
            $rules = $this->stepRules[$step] ?? [];
            
            if (!empty($rules)) {
                $validator = new \Elementary\Validation\Validator($this->getPublicProperties(), $rules);
                
                if ($validator->fails()) {
                    $allErrors["step_{$step}"] = $validator->errors();
                }
            }
        }
        
        return $allErrors;
    }
    
    public function nextStep(): void
    {
        if ($this->validateCurrentStep() && $this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }
    
    public function getStepValidationStatus(): array
    {
        $status = [];
        
        for ($step = 1; $step <= $this->totalSteps; $step++) {
            $rules = $this->stepRules[$step] ?? [];
            
            if (!empty($rules)) {
                $validator = new \Elementary\Validation\Validator($this->getPublicProperties(), $rules);
                $status[$step] = !$validator->fails();
            } else {
                $status[$step] = true;
            }
        }
        
        return $status;
    }
}
```

## Advanced Validation Patterns

### Async Validation

```php
class AsyncValidationComponent extends SparkComponent
{
    public string $username = '';
    public string $domain = '';
    
    public bool $isValidatingUsername = false;
    public bool $isValidatingDomain = false;
    public array $asyncErrors = [];
    
    protected function updated(string $property, mixed $value): void
    {
        if ($property === 'username') {
            $this->validateUsernameAsync();
        } elseif ($property === 'domain') {
            $this->validateDomainAsync();
        }
    }
    
    private function validateUsernameAsync(): void
    {
        if (strlen($this->username) < 3) {
            return;
        }
        
        $this->isValidatingUsername = true;
        
        // Simulate async validation (in real app, this would be an AJAX call)
        setTimeout(function() {
            $isAvailable = $this->checkUsernameAvailability($this->username);
            
            if ($isAvailable) {
                unset($this->asyncErrors['username']);
            } else {
                $this->asyncErrors['username'] = 'Username is already taken';
            }
            
            $this->isValidatingUsername = false;
        }, 1000);
    }
    
    private function validateDomainAsync(): void
    {
        if (empty($this->domain)) {
            return;
        }
        
        $this->isValidatingDomain = true;
        
        setTimeout(function() {
            $isValid = $this->validateDomainExists($this->domain);
            
            if ($isValid) {
                unset($this->asyncErrors['domain']);
            } else {
                $this->asyncErrors['domain'] = 'Domain does not exist or is not accessible';
            }
            
            $this->isValidatingDomain = false;
        }, 1500);
    }
    
    private function checkUsernameAvailability(string $username): bool
    {
        // Simulate database check
        $takenUsernames = ['admin', 'root', 'user', 'test'];
        return !in_array(strtolower($username), $takenUsernames);
    }
    
    private function validateDomainExists(string $domain): bool
    {
        // Simulate DNS lookup
        return checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
    }
}
```

### Cross-Field Validation

```php
class CrossFieldValidationComponent extends SparkComponent
{
    public string $startDate = '';
    public string $endDate = '';
    public float $minPrice = 0.0;
    public float $maxPrice = 0.0;
    public string $password = '';
    public string $currentPassword = '';
    
    public function validate(): array
    {
        $validator = new \Elementary\Validation\Validator($this->getPublicProperties(), [
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
            'minPrice' => 'required|numeric|min:0',
            'maxPrice' => 'required|numeric|min:0',
            'password' => 'required|min:8',
            'currentPassword' => 'required'
        ]);
        
        // Add cross-field validation
        $validator->after(function($validator) {
            // Validate date range
            if ($this->startDate && $this->endDate) {
                $start = new \DateTime($this->startDate);
                $end = new \DateTime($this->endDate);
                $diff = $start->diff($end)->days;
                
                if ($diff > 365) {
                    $validator->errors()->add('endDate', 'Date range cannot exceed 1 year');
                }
            }
            
            // Validate price range
            if ($this->minPrice > $this->maxPrice) {
                $validator->errors()->add('maxPrice', 'Maximum price must be greater than minimum price');
            }
            
            // Validate password change
            if (!empty($this->password) && !$this->verifyCurrentPassword($this->currentPassword)) {
                $validator->errors()->add('currentPassword', 'Current password is incorrect');
            }
            
            // Business rule validation
            if ($this->minPrice > 0 && $this->maxPrice > 0) {
                $ratio = $this->maxPrice / $this->minPrice;
                if ($ratio > 10) {
                    $validator->errors()->add('general', 'Price range is too wide (max 10x difference)');
                }
            }
        });
        
        return $validator->fails() ? $validator->errors() : [];
    }
    
    private function verifyCurrentPassword(string $password): bool
    {
        // Verify against stored password hash
        return password_verify($password, $this->getCurrentUserPasswordHash());
    }
}
```

Validation in Spark provides comprehensive, flexible validation capabilities that work seamlessly with the reactive component system, ensuring data integrity while providing excellent user experience.