<?php

namespace Vine\Support;

class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data, array $rules): static
    {
        $instance = new static($data);
        $instance->validate($rules);
        return $instance;
    }

    private function validate(array $rules): void
    {
        foreach ($rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $fieldRules = explode('|', $ruleString);

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        if (str_contains($rule, ':')) {
            [$ruleName, $param] = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $param = null;
        }

        switch ($ruleName) {
            case 'required':
                if ($value === null || $value === '') {
                    $this->errors[$field][] = "$field is required";
                }
                break;
            case 'string':
                if ($value !== null && !is_string($value)) {
                    $this->errors[$field][] = "$field must be a string";
                }
                break;
            case 'integer':
            case 'int':
                if ($value !== null && !is_int($value) && !ctype_digit((string)$value)) {
                    $this->errors[$field][] = "$field must be an integer";
                }
                break;
            case 'email':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = "$field must be a valid email";
                }
                break;
            case 'min':
                if ($value !== null && strlen((string)$value) < (int)$param) {
                    $this->errors[$field][] = "$field must be at least $param characters";
                }
                break;
            case 'max':
                if ($value !== null && strlen((string)$value) > (int)$param) {
                    $this->errors[$field][] = "$field must not exceed $param characters";
                }
                break;
            case 'in':
                $allowed = explode(',', $param);
                if ($value !== null && !in_array($value, $allowed)) {
                    $this->errors[$field][] = "$field must be one of: $param";
                }
                break;
            case 'numeric':
                if ($value !== null && !is_numeric($value)) {
                    $this->errors[$field][] = "$field must be numeric";
                }
                break;
            case 'array':
                if ($value !== null && !is_array($value)) {
                    $this->errors[$field][] = "$field must be an array";
                }
                break;
            case 'nullable':
                break;
        }
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->data;
    }
}
