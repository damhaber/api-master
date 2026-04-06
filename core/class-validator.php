<?php
if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Validator
{
    private $errors = [];
    private $rules = [];
    private static $customValidators = [];
    private $config = [];

    public function __construct()
    {
        $this->errors = [];
        $this->rules = [];
        
        $moduleDir = dirname(__DIR__, 1);
        $settingsFile = $moduleDir . '/config/settings.json';
        
        if (file_exists($settingsFile)) {
            $content = file_get_contents($settingsFile);
            $configData = json_decode($content, true);
            if (is_array($configData)) {
                $this->config = $configData;
            }
        }
    }

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];
        $this->rules = $rules;
        
        foreach ($rules as $field => $fieldRules) {
            $value = isset($data[$field]) ? $data[$field] : null;
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            
            foreach ($fieldRules as $rule) {
                $ruleName = $rule;
                $ruleParam = null;
                
                if (strpos($rule, ':') !== false) {
                    $parts = explode(':', $rule, 2);
                    $ruleName = $parts[0];
                    $ruleParam = $parts[1];
                }
                
                $methodName = 'validate' . ucfirst($ruleName);
                
                if (isset(self::$customValidators[$ruleName])) {
                    $callback = self::$customValidators[$ruleName];
                    if (!$callback($value, $ruleParam, $data)) {
                        $this->addError($field, $ruleName, $ruleParam);
                    }
                } elseif (method_exists($this, $methodName)) {
                    if (!$this->$methodName($value, $ruleParam, $data)) {
                        $this->addError($field, $ruleName, $ruleParam);
                    }
                }
            }
        }
        
        return empty($this->errors);
    }

    private function addError($field, $rule, $param = null)
    {
        $message = $this->getDefaultMessage($rule, $field, $param);
        
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }

    private function getDefaultMessage($rule, $field, $param = null)
    {
        $messages = [
            'required' => "{$field} alanı zorunludur.",
            'email' => "{$field} alanı geçerli bir e-posta adresi olmalıdır.",
            'min' => "{$field} alanı minimum {$param} karakter olmalıdır.",
            'max' => "{$field} alanı maksimum {$param} karakter olmalıdır.",
            'numeric' => "{$field} alanı sayısal olmalıdır.",
            'integer' => "{$field} alanı tam sayı olmalıdır.",
            'url' => "{$field} alanı geçerli bir URL olmalıdır.",
            'ip' => "{$field} alanı geçerli bir IP adresi olmalıdır.",
            'json' => "{$field} alanı geçerli bir JSON olmalıdır.",
            'array' => "{$field} alanı dizi olmalıdır.",
            'boolean' => "{$field} alanı boolean olmalıdır.",
            'date' => "{$field} alanı geçerli bir tarih olmalıdır.",
            'alpha' => "{$field} alanı sadece harflerden oluşmalıdır.",
            'alpha_num' => "{$field} alanı sadece harf ve rakamlardan oluşmalıdır.",
            'phone' => "{$field} alanı geçerli bir telefon numarası olmalıdır.",
            'in' => "{$field} alanı geçerli bir değer olmalıdır.",
            'same' => "{$field} alanı eşleşmiyor.",
            'different' => "{$field} alanı farklı olmalıdır.",
        ];
        
        return isset($messages[$rule]) ? $messages[$rule] : "{$field} alanı geçersizdir.";
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getFirstError()
    {
        if (empty($this->errors)) {
            return null;
        }
        
        $keys = array_keys($this->errors);
        $firstField = $keys[0];
        
        return isset($this->errors[$firstField][0]) ? $this->errors[$firstField][0] : null;
    }

    public static function sanitize($data, $type = 'string')
    {
        switch ($type) {
            case 'string':
                return htmlspecialchars(strip_tags(trim((string)$data)), ENT_QUOTES, 'UTF-8');
            case 'email':
                return filter_var(trim($data), FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var(trim($data), FILTER_SANITIZE_URL);
            case 'int':
            case 'integer':
                return (int)filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return (float)filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'alpha':
                return preg_replace('/[^a-zA-ZğüşıöçĞÜŞİÖÇ]/u', '', $data);
            case 'alpha_num':
                return preg_replace('/[^a-zA-Z0-9ğüşıöçĞÜŞİÖÇ]/u', '', $data);
            case 'array':
                if (is_array($data)) {
                    return array_map(function($item) {
                        return self::sanitize($item, 'string');
                    }, $data);
                }
                return [];
            case 'json':
                return json_encode($data);
            case 'bool':
            case 'boolean':
                return filter_var($data, FILTER_VALIDATE_BOOLEAN);
            default:
                return $data;
        }
    }

    public static function sanitizeDeep($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitizeDeep($value);
            }
            return $data;
        }
        
        if (is_string($data)) {
            return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
        }
        
        return $data;
    }

    private function validateRequired($value, $param = null, $data = [])
    {
        if (is_null($value)) {
            return false;
        }
        
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        
        if (is_array($value) && empty($value)) {
            return false;
        }
        
        return true;
    }

    private function validateEmail($value)
    {
        if (empty($value)) {
            return true;
        }
        
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateMin($value, $param)
    {
        if (empty($value)) {
            return true;
        }
        
        return mb_strlen($value) >= (int)$param;
    }

    private function validateMax($value, $param)
    {
        if (empty($value)) {
            return true;
        }
        
        return mb_strlen($value) <= (int)$param;
    }

    private function validateNumeric($value)
    {
        if (empty($value) && $value !== 0) {
            return true;
        }
        
        return is_numeric($value);
    }

    private function validateInteger($value)
    {
        if (empty($value) && $value !== 0) {
            return true;
        }
        
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private function validateUrl($value)
    {
        if (empty($value)) {
            return true;
        }
        
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateIp($value)
    {
        if (empty($value)) {
            return true;
        }
        
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    private function validateJson($value)
    {
        if (empty($value)) {
            return true;
        }
        
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function validateArray($value)
    {
        return is_array($value);
    }

    private function validateBoolean($value)
    {
        if (is_bool($value)) {
            return true;
        }
        
        $boolValues = ['true', 'false', '1', '0', 1, 0, 'yes', 'no'];
        return in_array(strtolower((string)$value), $boolValues, true);
    }

    private function validateDate($value, $format = 'Y-m-d')
    {
        if (empty($value)) {
            return true;
        }
        
        $date = \DateTime::createFromFormat($format, $value);
        return $date && $date->format($format) === $value;
    }

    private function validateAlpha($value)
    {
        if (empty($value)) {
            return true;
        }
        
        return preg_match('/^[a-zA-ZğüşıöçĞÜŞİÖÇ]+$/u', $value) === 1;
    }

    private function validateAlphaNum($value)
    {
        if (empty($value)) {
            return true;
        }
        
        return preg_match('/^[a-zA-Z0-9ğüşıöçĞÜŞİÖÇ]+$/u', $value) === 1;
    }

    private function validatePhone($value)
    {
        if (empty($value)) {
            return true;
        }
        
        return preg_match('/^[\+]?[0-9\s\-\(\)]{8,20}$/', $value) === 1;
    }

    private function validateIn($value, $param)
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return true;
        }
        
        $allowedValues = array_map('trim', explode(',', $param));
        return in_array($value, $allowedValues);
    }

    private function validateNotIn($value, $param)
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return true;
        }
        
        $disallowedValues = array_map('trim', explode(',', $param));
        return !in_array($value, $disallowedValues);
    }

    private function validateSame($value, $field, $data)
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return true;
        }
        
        $otherValue = isset($data[$field]) ? $data[$field] : null;
        return $value === $otherValue;
    }

    private function validateDifferent($value, $field, $data)
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return true;
        }
        
        $otherValue = isset($data[$field]) ? $data[$field] : null;
        return $value !== $otherValue;
    }

    private function validateRegex($value, $pattern)
    {
        if (empty($value)) {
            return true;
        }
        
        return preg_match($pattern, $value) === 1;
    }

    public static function addValidator($name, callable $callback)
    {
        self::$customValidators[$name] = $callback;
    }

    public static function validateApiKey($apiKey, $provider = '')
    {
        if (empty($apiKey)) {
            return false;
        }
        
        $patterns = [
            'openai' => '/^sk-[a-zA-Z0-9]{48}$/',
            'deepseek' => '/^sk-[a-zA-Z0-9]{32,64}$/',
            'gemini' => '/^AIza[A-Za-z0-9_-]{35}$/',
            'claude' => '/^sk-ant-[a-zA-Z0-9_-]{40,100}$/',
            'anthropic' => '/^sk-ant-[a-zA-Z0-9_-]{40,100}$/',
            'mistral' => '/^[a-zA-Z0-9]{32,64}$/',
            'cohere' => '/^[a-zA-Z0-9]{32,64}$/'
        ];
        
        if ($provider && isset($patterns[$provider])) {
            return preg_match($patterns[$provider], $apiKey) === 1;
        }
        
        return preg_match('/^[a-zA-Z0-9_\-]{20,100}$/', $apiKey) === 1;
    }

    public static function validateHttpMethod($method)
    {
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
        return in_array(strtoupper($method), $validMethods);
    }

    public static function validateMimeType($mimeType)
    {
        $validTypes = [
            'application/json',
            'application/xml',
            'text/plain',
            'text/html',
            'multipart/form-data',
            'application/x-www-form-urlencoded'
        ];
        
        return in_array($mimeType, $validTypes);
    }

    public function validateProvider($provider)
    {
        $moduleDir = dirname(__DIR__, 1);
        $providersFile = $moduleDir . '/config/providers.json';
        
        if (file_exists($providersFile)) {
            $content = file_get_contents($providersFile);
            $providers = json_decode($content, true);
            if (is_array($providers)) {
                return isset($providers[$provider]);
            }
        }
        
        return false;
    }

    public function validateConfigKey($key, $section = '')
    {
        if (empty($section)) {
            return isset($this->config[$key]);
        }
        
        return isset($this->config[$section][$key]);
    }
}