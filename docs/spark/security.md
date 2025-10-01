# Security

This guide covers security considerations and best practices for Spark components, including CSRF protection, input validation, authorization, and secure data handling.

## Table of Contents

1. [Security Overview](#security-overview)
2. [CSRF Protection](#csrf-protection)
3. [Input Validation & Sanitization](#input-validation--sanitization)
4. [Authorization & Access Control](#authorization--access-control)
5. [Secure Data Handling](#secure-data-handling)
6. [XSS Prevention](#xss-prevention)
7. [Session Security](#session-security)
8. [Best Practices](#best-practices)

## Security Overview

Spark components handle sensitive user interactions and data, making security a critical consideration. The framework provides multiple layers of protection:

### Security Layers

```
┌─────────────────────────────────────────────────────────────┐
│                    Spark Security Layers                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Frontend Security          Backend Security               │
│  ┌─────────────────────┐    ┌────────────────────────────┐  │
│  │ Input Sanitization  │    │ Request Validation         │  │
│  │ XSS Prevention      │    │ CSRF Protection            │  │
│  │ DOM Protection      │    │ Authorization Checks       │  │
│  └─────────────────────┘    │ Data Sanitization          │  │
│           │                 │ Session Security           │  │
│           │                 └────────────────────────────┘  │
│           ▼                              │                  │
│  ┌─────────────────────┐                 ▼                  │
│  │ HTTPS Transport     │◄────────────────────────────────┐  │
│  │ Secure Headers      │                                 │  │
│  │ Content Security    │                                 │  │
│  └─────────────────────┘                                 │  │
│                                                          │  │
│  ┌────────────────────────────────────────────────────────┐ │
│  │                 Middleware Pipeline                   │ │
│  │ ValidateJsonPayload → ValidateSparkRequest →          │ │
│  │ ValidateSparkChecksum → Authorization → [Handler]     │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

## CSRF Protection

### Automatic CSRF Protection

Spark automatically includes CSRF protection for all component requests:

```php
class SecureFormComponent extends SparkComponent
{
    public string $sensitiveData = '';
    public bool $isAdmin = false;
    
    public function processSensitiveAction(): void
    {
        // CSRF token is automatically validated by middleware
        // before this method is called
        
        if (!$this->isAuthorized()) {
            throw new \Exception('Unauthorized action');
        }
        
        $this->performSensitiveOperation();
    }
    
    private function isAuthorized(): bool
    {
        return $this->getCurrentUser()?->hasPermission('sensitive_action');
    }
}
```

### Custom CSRF Implementation

```php
class CustomCSRFComponent extends SparkComponent
{
    private string $csrfToken;
    
    protected function mount(): void
    {
        $this->csrfToken = $this->generateCSRFToken();
    }
    
    public function validateRequest(Request $request): bool
    {
        $providedToken = $request->headers->get('X-CSRF-Token');
        
        if (!$providedToken || !hash_equals($this->csrfToken, $providedToken)) {
            $this->log('security', 'CSRF token validation failed', [
                'user_id' => $this->getCurrentUser()?->id,
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);
            
            throw new \Exception('CSRF token validation failed');
        }
        
        return true;
    }
    
    private function generateCSRFToken(): string
    {
        return hash_hmac('sha256', session_id() . microtime(), config('app.key'));
    }
    
    public function getCSRFToken(): string
    {
        return $this->csrfToken;
    }
}
```

## Input Validation & Sanitization

### Comprehensive Input Validation

```php
class SecureInputComponent extends SparkComponent
{
    public string $userInput = '';
    public string $htmlContent = '';
    public array $fileUploads = [];
    
    protected array $rules = [
        'userInput' => 'required|string|max:1000|no_scripts',
        'htmlContent' => 'required|string|safe_html',
        'fileUploads.*' => 'file|max:10240|safe_file_type'
    ];
    
    public function validate(): array
    {
        $validator = new \Elementary\Validation\Validator($this->getPublicProperties(), $this->rules);
        
        // Add custom security validation rules
        $validator->extend('no_scripts', function($field, $value, $parameters) {
            return !$this->containsScripts($value);
        }, 'The :field contains potentially dangerous scripts.');
        
        $validator->extend('safe_html', function($field, $value, $parameters) {
            return $this->isHTMLSafe($value);
        }, 'The :field contains unsafe HTML content.');
        
        $validator->extend('safe_file_type', function($field, $value, $parameters) {
            return $this->isFileTypeSafe($value);
        }, 'The :field is not a safe file type.');
        
        return $validator->fails() ? $validator->errors() : [];
    }
    
    protected function updated(string $property, mixed $value): void
    {
        // Sanitize input in real-time
        switch ($property) {
            case 'userInput':
                $this->userInput = $this->sanitizeText($value);
                break;
            case 'htmlContent':
                $this->htmlContent = $this->sanitizeHTML($value);
                break;
        }
    }
    
    private function containsScripts(string $input): bool
    {
        $dangerousPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/eval\s*\(/i',
            '/expression\s*\(/i'
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isHTMLSafe(string $html): bool
    {
        // Use HTML Purifier or similar library
        $allowedTags = ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a'];
        $allowedAttributes = ['href', 'title', 'class'];
        
        return $this->validateHTMLStructure($html, $allowedTags, $allowedAttributes);
    }
    
    private function sanitizeText(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Normalize line endings
        $input = str_replace(["\r\n", "\r"], "\n", $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Remove control characters except tabs and newlines
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        return $input;
    }
    
    private function sanitizeHTML(string $html): string
    {
        // Use a proper HTML sanitizer like HTML Purifier
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,em,ul,ol,li,a[href|title]');
        
        $purifier = new HTMLPurifier($config);
        return $purifier->purify($html);
    }
}
```

### SQL Injection Prevention

```php
class DatabaseSecurityComponent extends SparkComponent
{
    public string $searchTerm = '';
    public int $userId = 0;
    public array $results = [];
    
    public function searchUsers(): void
    {
        // ❌ DANGEROUS - Never do this
        // $sql = "SELECT * FROM users WHERE name LIKE '%{$this->searchTerm}%'";
        
        // ✅ SAFE - Use parameterized queries
        $this->results = $this->searchUsersSecurely($this->searchTerm);
    }
    
    private function searchUsersSecurely(string $term): array
    {
        // Using ElementaryORM (safe by default)
        return User::where('name', 'LIKE', "%{$term}%")
                  ->where('active', true)
                  ->limit(50)
                  ->get()
                  ->toArray();
    }
    
    public function getUserById(int $id): ?array
    {
        // Validate input
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid user ID');
        }
        
        // Use parameterized query
        $user = User::find($id);
        
        // Check authorization
        if ($user && !$this->canViewUser($user)) {
            throw new \Exception('Access denied');
        }
        
        return $user?->toArray();
    }
    
    private function canViewUser(User $user): bool
    {
        $currentUser = $this->getCurrentUser();
        
        return $currentUser && (
            $currentUser->id === $user->id ||
            $currentUser->hasRole('admin') ||
            $currentUser->hasPermission('view_all_users')
        );
    }
}
```

## Authorization & Access Control

### Role-Based Access Control

```php
class RBACComponent extends SparkComponent
{
    public array $users = [];
    public string $selectedUserId = '';
    
    protected function mount(): void
    {
        // Check if user has permission to access this component
        if (!$this->hasPermission('manage_users')) {
            throw new \Exception('Access denied: Insufficient permissions');
        }
        
        $this->loadUsers();
    }
    
    public function deleteUser(string $userId): void
    {
        // Double-check permissions for sensitive operations
        if (!$this->hasPermission('delete_users')) {
            $this->logSecurityEvent('unauthorized_delete_attempt', [
                'target_user_id' => $userId,
                'attempted_by' => $this->getCurrentUser()?->id
            ]);
            throw new \Exception('Access denied: Cannot delete users');
        }
        
        $targetUser = User::find($userId);
        
        if (!$targetUser) {
            throw new \Exception('User not found');
        }
        
        // Prevent deletion of higher-privileged users
        if (!$this->canManageUser($targetUser)) {
            throw new \Exception('Cannot delete user with equal or higher privileges');
        }
        
        // Log the action
        $this->logSecurityEvent('user_deleted', [
            'deleted_user_id' => $userId,
            'deleted_by' => $this->getCurrentUser()->id
        ]);
        
        $targetUser->delete();
        $this->loadUsers(); // Refresh the list
    }
    
    public function changeUserRole(string $userId, string $newRole): void
    {
        if (!$this->hasPermission('manage_roles')) {
            throw new \Exception('Access denied: Cannot manage roles');
        }
        
        $targetUser = User::find($userId);
        $currentUser = $this->getCurrentUser();
        
        // Security checks
        if (!$this->canAssignRole($newRole)) {
            throw new \Exception('Cannot assign role higher than your own');
        }
        
        if (!$this->canManageUser($targetUser)) {
            throw new \Exception('Cannot modify user with equal or higher privileges');
        }
        
        $oldRole = $targetUser->role;
        $targetUser->role = $newRole;
        $targetUser->save();
        
        $this->logSecurityEvent('role_changed', [
            'user_id' => $userId,
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'changed_by' => $currentUser->id
        ]);
    }
    
    private function hasPermission(string $permission): bool
    {
        return $this->getCurrentUser()?->hasPermission($permission) ?? false;
    }
    
    private function canManageUser(User $targetUser): bool
    {
        $currentUser = $this->getCurrentUser();
        
        if (!$currentUser) {
            return false;
        }
        
        // Super admins can manage anyone
        if ($currentUser->hasRole('super_admin')) {
            return true;
        }
        
        // Users cannot manage themselves for critical operations
        if ($currentUser->id === $targetUser->id) {
            return false;
        }
        
        // Check role hierarchy
        return $currentUser->getRoleLevel() > $targetUser->getRoleLevel();
    }
    
    private function canAssignRole(string $role): bool
    {
        $currentUser = $this->getCurrentUser();
        $roleLevel = Role::getLevelByName($role);
        
        return $currentUser->getRoleLevel() >= $roleLevel;
    }
}
```

### Resource-Based Access Control

```php
class ResourceAccessComponent extends SparkComponent
{
    public string $documentId = '';
    public array $documentData = [];
    public bool $canEdit = false;
    public bool $canDelete = false;
    public bool $canShare = false;
    
    protected function mount(): void
    {
        if (!empty($this->documentId)) {
            $this->loadDocument();
        }
    }
    
    private function loadDocument(): void
    {
        $document = Document::find($this->documentId);
        
        if (!$document) {
            throw new \Exception('Document not found');
        }
        
        // Check read access
        if (!$this->canReadDocument($document)) {
            throw new \Exception('Access denied: Cannot read document');
        }
        
        $this->documentData = $document->toArray();
        
        // Set permission flags
        $this->canEdit = $this->canEditDocument($document);
        $this->canDelete = $this->canDeleteDocument($document);
        $this->canShare = $this->canShareDocument($document);
    }
    
    public function updateDocument(array $data): void
    {
        $document = Document::find($this->documentId);
        
        if (!$this->canEditDocument($document)) {
            throw new \Exception('Access denied: Cannot edit document');
        }
        
        // Validate and sanitize data
        $cleanData = $this->sanitizeDocumentData($data);
        
        $document->update($cleanData);
        
        $this->logSecurityEvent('document_updated', [
            'document_id' => $this->documentId,
            'updated_by' => $this->getCurrentUser()->id,
            'changes' => array_keys($cleanData)
        ]);
        
        $this->loadDocument(); // Refresh data
    }
    
    private function canReadDocument(Document $document): bool
    {
        $user = $this->getCurrentUser();
        
        return $document->isPublic() ||
               $document->owner_id === $user->id ||
               $document->hasSharedAccess($user->id) ||
               $user->hasPermission('read_all_documents');
    }
    
    private function canEditDocument(Document $document): bool
    {
        $user = $this->getCurrentUser();
        
        return $document->owner_id === $user->id ||
               $document->hasEditAccess($user->id) ||
               $user->hasPermission('edit_all_documents');
    }
    
    private function canDeleteDocument(Document $document): bool
    {
        $user = $this->getCurrentUser();
        
        return $document->owner_id === $user->id ||
               $user->hasPermission('delete_all_documents');
    }
    
    private function canShareDocument(Document $document): bool
    {
        return $this->canEditDocument($document);
    }
}
```

## Secure Data Handling

### Sensitive Data Protection

```php
class SensitiveDataComponent extends SparkComponent
{
    public string $creditCardNumber = '';
    public string $ssn = '';
    public string $password = '';
    
    // Never expose sensitive data as public properties
    private string $encryptedCreditCard = '';
    private string $hashedPassword = '';
    
    public function saveCreditCard(string $cardNumber): void
    {
        // Validate credit card format
        if (!$this->isValidCreditCard($cardNumber)) {
            throw new \Exception('Invalid credit card number');
        }
        
        // Encrypt sensitive data before storage
        $this->encryptedCreditCard = $this->encryptSensitiveData($cardNumber);
        
        // Only store last 4 digits for display
        $this->creditCardNumber = '**** **** **** ' . substr($cardNumber, -4);
        
        $this->logSecurityEvent('credit_card_saved', [
            'user_id' => $this->getCurrentUser()->id,
            'card_last_four' => substr($cardNumber, -4)
        ]);
    }
    
    public function updatePassword(string $currentPassword, string $newPassword): void
    {
        // Verify current password
        if (!$this->verifyCurrentPassword($currentPassword)) {
            $this->logSecurityEvent('password_change_failed', [
                'user_id' => $this->getCurrentUser()->id,
                'reason' => 'invalid_current_password'
            ]);
            throw new \Exception('Current password is incorrect');
        }
        
        // Validate new password strength
        if (!$this->isStrongPassword($newPassword)) {
            throw new \Exception('Password does not meet security requirements');
        }
        
        // Hash and store new password
        $this->hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
        
        $this->logSecurityEvent('password_changed', [
            'user_id' => $this->getCurrentUser()->id
        ]);
        
        // Clear password from component state
        $this->password = '';
    }
    
    private function encryptSensitiveData(string $data): string
    {
        $key = base64_decode(config('app.encryption_key'));
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    private function decryptSensitiveData(string $encryptedData): string
    {
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $key = base64_decode(config('app.encryption_key'));
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    // Never include sensitive data in component state
    public function getPublicProperties(): array
    {
        $properties = parent::getPublicProperties();
        
        // Remove sensitive fields
        unset($properties['password']);
        
        return $properties;
    }
}
```

### Data Masking and Redaction

```php
class DataMaskingComponent extends SparkComponent
{
    public array $users = [];
    public bool $showSensitiveData = false;
    
    protected function mount(): void
    {
        $this->loadUsers();
    }
    
    private function loadUsers(): void
    {
        $rawUsers = User::all()->toArray();
        
        $this->users = array_map(function($user) {
            return $this->maskSensitiveData($user);
        }, $rawUsers);
    }
    
    public function toggleSensitiveData(): void
    {
        // Only admins can view unmasked data
        if (!$this->getCurrentUser()->hasRole('admin')) {
            throw new \Exception('Access denied: Cannot view sensitive data');
        }
        
        $this->showSensitiveData = !$this->showSensitiveData;
        
        $this->logSecurityEvent('sensitive_data_toggled', [
            'user_id' => $this->getCurrentUser()->id,
            'show_sensitive' => $this->showSensitiveData
        ]);
        
        $this->loadUsers();
    }
    
    private function maskSensitiveData(array $user): array
    {
        if ($this->showSensitiveData && $this->getCurrentUser()->hasRole('admin')) {
            return $user; // Return unmasked data for admins
        }
        
        // Mask sensitive fields
        if (isset($user['email'])) {
            $user['email'] = $this->maskEmail($user['email']);
        }
        
        if (isset($user['phone'])) {
            $user['phone'] = $this->maskPhone($user['phone']);
        }
        
        if (isset($user['ssn'])) {
            $user['ssn'] = 'XXX-XX-' . substr($user['ssn'], -4);
        }
        
        // Remove completely sensitive fields
        unset($user['password_hash'], $user['api_token']);
        
        return $user;
    }
    
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***.***';
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        $maskedUsername = substr($username, 0, 2) . str_repeat('*', max(0, strlen($username) - 2));
        $domainParts = explode('.', $domain);
        $maskedDomain = substr($domainParts[0], 0, 1) . str_repeat('*', max(0, strlen($domainParts[0]) - 1));
        
        if (isset($domainParts[1])) {
            $maskedDomain .= '.' . $domainParts[1];
        }
        
        return $maskedUsername . '@' . $maskedDomain;
    }
    
    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        
        if (strlen($digits) >= 4) {
            return str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
        }
        
        return str_repeat('*', strlen($digits));
    }
}
```

## XSS Prevention

### Output Escaping

```php
class XSSPreventionComponent extends SparkComponent
{
    public string $userContent = '';
    public string $htmlContent = '';
    public array $userComments = [];
    
    public function addComment(string $content): void
    {
        // Sanitize input
        $cleanContent = $this->sanitizeUserInput($content);
        
        $comment = [
            'id' => uniqid(),
            'content' => $cleanContent,
            'author' => $this->getCurrentUser()->name,
            'created_at' => time(),
            'is_safe' => $this->isSafeContent($cleanContent)
        ];
        
        $this->userComments[] = $comment;
    }
    
    private function sanitizeUserInput(string $input): string
    {
        // Remove potential XSS vectors
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove dangerous patterns
        $dangerousPatterns = [
            '/javascript:/i',
            '/vbscript:/i',
            '/on\w+\s*=/i',
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi'
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }
        
        return $input;
    }
    
    private function isSafeContent(string $content): bool
    {
        // Check for potential XSS indicators
        $xssPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];
        
        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }
        
        return true;
    }
    
    // Template helper for safe output
    public function safeOutput(string $content): string
    {
        return htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
```

## Session Security

### Secure Session Management

```php
class SecureSessionComponent extends SparkComponent
{
    public bool $isLoggedIn = false;
    public array $sessionInfo = [];
    
    protected function mount(): void
    {
        $this->validateSession();
        $this->loadSessionInfo();
    }
    
    public function login(string $username, string $password): void
    {
        // Rate limiting
        if ($this->isRateLimited($username)) {
            throw new \Exception('Too many login attempts. Please try again later.');
        }
        
        $user = $this->authenticateUser($username, $password);
        
        if (!$user) {
            $this->recordFailedLogin($username);
            throw new \Exception('Invalid credentials');
        }
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        // Set secure session data
        $_SESSION['user_id'] = $user->id;
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        
        $this->isLoggedIn = true;
        
        $this->logSecurityEvent('user_logged_in', [
            'user_id' => $user->id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]);
    }
    
    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        
        // Clear session data
        session_unset();
        session_destroy();
        
        // Start new session
        session_start();
        session_regenerate_id(true);
        
        $this->isLoggedIn = false;
        $this->sessionInfo = [];
        
        if ($userId) {
            $this->logSecurityEvent('user_logged_out', [
                'user_id' => $userId
            ]);
        }
    }
    
    private function validateSession(): void
    {
        if (!isset($_SESSION['user_id'])) {
            return;
        }
        
        // Check session age
        $maxAge = 8 * 60 * 60; // 8 hours
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $maxAge) {
            $this->logout();
            throw new \Exception('Session expired');
        }
        
        // Check IP address consistency (optional, can break with legitimate IP changes)
        if (config('security.check_ip') && isset($_SESSION['ip_address'])) {
            if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
                $this->logSecurityEvent('session_ip_mismatch', [
                    'user_id' => $_SESSION['user_id'],
                    'original_ip' => $_SESSION['ip_address'],
                    'current_ip' => $_SERVER['REMOTE_ADDR']
                ]);
                
                $this->logout();
                throw new \Exception('Session security violation');
            }
        }
        
        // Check user agent consistency
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            $this->logSecurityEvent('session_user_agent_mismatch', [
                'user_id' => $_SESSION['user_id']
            ]);
            
            // Don't auto-logout for user agent changes (too common)
            // But log the event for monitoring
        }
        
        $this->isLoggedIn = true;
    }
    
    private function isRateLimited(string $username): bool
    {
        $key = 'login_attempts_' . hash('sha256', $username . $_SERVER['REMOTE_ADDR']);
        $attempts = $_SESSION[$key] ?? 0;
        $lastAttempt = $_SESSION[$key . '_time'] ?? 0;
        
        // Reset counter after 15 minutes
        if (time() - $lastAttempt > 900) {
            unset($_SESSION[$key], $_SESSION[$key . '_time']);
            return false;
        }
        
        return $attempts >= 5;
    }
    
    private function recordFailedLogin(string $username): void
    {
        $key = 'login_attempts_' . hash('sha256', $username . $_SERVER['REMOTE_ADDR']);
        $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
        $_SESSION[$key . '_time'] = time();
        
        $this->logSecurityEvent('failed_login_attempt', [
            'username' => $username,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'attempts' => $_SESSION[$key]
        ]);
    }
}
```

## Best Practices

### Security Checklist

```php
class SecurityChecklistComponent extends SparkComponent
{
    // ✅ 1. Always validate and sanitize input
    protected function updated(string $property, mixed $value): void
    {
        $this->$property = $this->sanitizeInput($value);
        $this->validateProperty($property);
    }
    
    // ✅ 2. Use parameterized queries
    private function searchDatabase(string $term): array
    {
        return User::where('name', 'LIKE', "%{$term}%")->get()->toArray();
    }
    
    // ✅ 3. Implement proper authorization
    public function sensitiveAction(): void
    {
        if (!$this->isAuthorized('sensitive_action')) {
            throw new \Exception('Access denied');
        }
        
        $this->performAction();
    }
    
    // ✅ 4. Log security events
    private function logSecurityEvent(string $event, array $context = []): void
    {
        $this->log('security', $event, array_merge($context, [
            'timestamp' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]));
    }
    
    // ✅ 5. Never expose sensitive data
    public function getPublicProperties(): array
    {
        $properties = parent::getPublicProperties();
        
        // Remove sensitive fields
        $sensitiveFields = ['password', 'api_key', 'private_data'];
        foreach ($sensitiveFields as $field) {
            unset($properties[$field]);
        }
        
        return $properties;
    }
    
    // ✅ 6. Implement rate limiting
    private function checkRateLimit(string $action): bool
    {
        $key = "rate_limit_{$action}_" . $this->getCurrentUser()->id;
        $attempts = cache($key, 0);
        
        if ($attempts >= 10) { // 10 attempts per minute
            return false;
        }
        
        cache($key, $attempts + 1, 60); // 1 minute TTL
        return true;
    }
    
    // ✅ 7. Use HTTPS in production
    private function enforceHTTPS(): void
    {
        if (!isset($_SERVER['HTTPS']) && config('app.env') === 'production') {
            throw new \Exception('HTTPS required for this operation');
        }
    }
    
    // ✅ 8. Validate file uploads
    private function validateFileUpload($file): bool
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        return in_array($file->getMimeType(), $allowedTypes) &&
               $file->getSize() <= $maxSize &&
               $this->isFileContentSafe($file);
    }
    
    // ✅ 9. Implement Content Security Policy
    public function getSecurityHeaders(): array
    {
        return [
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'",
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains'
        ];
    }
    
    // ✅ 10. Regular security audits
    public function performSecurityAudit(): array
    {
        return [
            'csrf_protection' => $this->checkCSRFProtection(),
            'input_validation' => $this->checkInputValidation(),
            'authorization' => $this->checkAuthorization(),
            'secure_headers' => $this->checkSecureHeaders(),
            'session_security' => $this->checkSessionSecurity()
        ];
    }
}
```

Security is paramount in Spark components. Always validate input, implement proper authorization, log security events, and follow secure coding practices to protect your application and users.