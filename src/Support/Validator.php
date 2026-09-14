<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Small rule-based validator. Rules are declared per field as a pipe-delimited
 * string, e.g. 'required|string|max:120'. Unknown fields are ignored; the
 * validated() result only ever contains declared fields, which keeps request
 * payloads from reaching the database unchecked.
 */
class Validator
{
    private array $data;

    private array $rules;

    private array $errors = [];

    private array $valid = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    /**
     * @throws ValidationException
     */
    public function validate(): array
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $present = array_key_exists($field, $this->data);
            $value = $this->data[$field] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            // Treat an empty string as "not provided" so optional fields clear cleanly.
            if ($value === '') {
                $value = null;
            }

            if (in_array('sometimes', $rules, true) && ! $present) {
                continue;
            }

            if (in_array('required', $rules, true) && ($value === null || $value === [])) {
                $this->errors[$field][] = $this->label($field).' is required.';

                continue;
            }

            if ($value === null) {
                if (in_array('nullable', $rules, true) || ! in_array('required', $rules, true)) {
                    $this->valid[$field] = null;
                }

                continue;
            }

            $value = $this->applyRules($field, $value, $rules);

            if (! isset($this->errors[$field])) {
                $this->valid[$field] = $value;
            }
        }

        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }

        return $this->valid;
    }

    private function applyRules(string $field, $value, array $rules)
    {
        foreach ($rules as $rule) {
            [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

            switch ($name) {
                case 'string':
                    if (! is_string($value)) {
                        $this->errors[$field][] = $this->label($field).' must be text.';
                    }
                    break;

                case 'integer':
                    if (! is_numeric($value) || (string) (int) $value !== (string) $value) {
                        $this->errors[$field][] = $this->label($field).' must be a whole number.';
                    } else {
                        $value = (int) $value;
                    }
                    break;

                case 'boolean':
                    $bool = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                    if ($bool === null) {
                        $this->errors[$field][] = $this->label($field).' must be true or false.';
                    } else {
                        $value = $bool;
                    }
                    break;

                case 'array':
                    if (! is_array($value)) {
                        $this->errors[$field][] = $this->label($field).' must be a list.';
                    }
                    break;

                case 'max':
                    if (is_string($value) && mb_strlen($value) > (int) $parameter) {
                        $this->errors[$field][] = sprintf('%s may not be longer than %d characters.', $this->label($field), (int) $parameter);
                    } elseif (is_array($value) && count($value) > (int) $parameter) {
                        $this->errors[$field][] = sprintf('%s may not have more than %d items.', $this->label($field), (int) $parameter);
                    }
                    break;

                case 'min':
                    if (is_array($value) && count($value) < (int) $parameter) {
                        $this->errors[$field][] = sprintf('%s must have at least %d items.', $this->label($field), (int) $parameter);
                    }
                    break;

                case 'between':
                    [$low, $high] = array_map('intval', explode(',', (string) $parameter));
                    if (! is_numeric($value) || (int) $value < $low || (int) $value > $high) {
                        $this->errors[$field][] = sprintf('%s must be between %d and %d.', $this->label($field), $low, $high);
                    } else {
                        $value = (int) $value;
                    }
                    break;

                case 'in':
                    $allowed = explode(',', (string) $parameter);
                    if (! in_array((string) $value, $allowed, true)) {
                        $this->errors[$field][] = $this->label($field).' is not a valid option.';
                    }
                    break;

                case 'url':
                    // Only http/https; this value is rendered as a link.
                    $scheme = is_string($value) ? strtolower((string) parse_url($value, PHP_URL_SCHEME)) : '';
                    if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false || ! in_array($scheme, ['http', 'https'], true)) {
                        $this->errors[$field][] = $this->label($field).' must be a valid http or https URL.';
                    }
                    break;
            }
        }

        return $value;
    }

    private function label(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }
}
