<?php

declare(strict_types=1);

namespace Elementary\Validation;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public function passes(): bool
    {
        foreach ($this->rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        // Split rule name and parameters (e.g., minLength:8)
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $ruleParam = $ruleParts[1] ?? null;

        $methodName = 'validate' . ucfirst($ruleName);

        if (method_exists($this, $methodName)) {
            $this->$methodName($field, $value, $ruleParam);
        }
    }

    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    // --- Validation Rules ---

    private function validateRequired(string $field, mixed $value): void
    {
        if (empty($value)) {
            $this->addError($field, "The {$field} field is required.");
        }
    }

    private function validateEmail(string $field, mixed $value): void
    {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "The {$field} must be a valid email address.");
        }
    }

    private function validateMinLength(string $field, mixed $value, string $length): void
    {
        if (!empty($value) && strlen(trim($value)) < (int)$length) {
            $this->addError($field, "The {$field} must be at least {$length} characters.");
        }
    }
}
