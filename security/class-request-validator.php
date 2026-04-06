<?php
/**
 * API Master - Request Validator
 * 
 * @package APIMaster
 * @subpackage Security
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class APIMaster_Request_Validator
 * 
 * İstek doğrulama ve sanitizasyon
 */
class APIMaster_Request_Validator {
    
    /**
     * @var array Validation rules
     */
    private $rules = [];
    
    /**
     * @var array Validation errors
     */
    private $errors = [];
    
    /**
     * Validasyon kuralı ekle
     * 
     * @param string $field
     * @param string $rule
     * @param mixed $parameter
     */
    public function addRule($field, $rule, $parameter = null) {
        if (!isset($this->rules[$field])) {
            $this->rules[$field] = [];
        }
        
        $this->rules[$field][] = [
            'rule' => $rule,
            'parameter' => $parameter
        ];
    }
    
    /**
     * Veriyi doğrula
     * 
     * @param array $data
     * @param array|null $rules
     * @return bool
     */
    public function validate($data, $rules = null) {
        $this->errors = [];
        $rulesToApply = $rules ?? $this->rules;
        
        foreach ($rulesToApply as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            foreach ($fieldRules as $ruleConfig) {
                $rule = $ruleConfig['rule'];
                $parameter = $ruleConfig['parameter'] ?? null;
                
                if (!$this->applyRule($field, $value, $rule, $parameter)) {
                    break; // Bir kural başarısız olduysa diğerlerini kontrol etme
                }
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Kuralı uygula
     * 
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @param mixed $parameter
     * @return bool
     */
    private function applyRule($field, $value, $rule, $parameter = null) {
        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    $this->addError($field, 'Bu alan zorunludur');
                    return false;
                }
                break;
                
            case 'string':
                if (!is_string($value)) {
                    $this->addError($field, 'Bu alan metin olmalıdır');
                    return false;
                }
                break;
                
            case 'int':
            case 'integer':
                if (!is_numeric($value) || (int)$value != $value) {
                    $this->addError($field, 'Bu alan tam sayı olmalıdır');
                    return false;
                }
                break;
                
            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, 'Bu alan sayı olmalıdır');
                    return false;
                }
                break;
                
            case 'boolean':
                if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true)) {
                    $this->addError($field, 'Bu alan boolean olmalıdır');
                    return false;
                }
                break;
                
            case 'array':
                if (!is_array($value)) {
                    $this->addError($field, 'Bu alan dizi olmalıdır');
                    return false;
                }
                break;
                
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'Geçerli bir email adresi giriniz');
                    return false;
                }
                break;
                
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, 'Geçerli bir URL giriniz');
                    return false;
                }
                break;
                
            case 'ip':
                if (!filter_var($value, FILTER_VALIDATE_IP)) {
                    $this->addError($field, 'Geçerli bir IP adresi giriniz');
                    return false;
                }
                break;
                
            case 'min':
                if (strlen($value) < $parameter) {
                    $this->addError($field, "Bu alan en az {$parameter} karakter olmalıdır");
                    return false;
                }
                break;
                
            case 'max':
                if (strlen($value) > $parameter) {
                    $this->addError($field, "Bu alan en fazla {$parameter} karakter olmalıdır");
                    return false;
                }
                break;
                
            case 'min_value':
                if ($value < $parameter) {
                    $this->addError($field, "Bu alan en az {$parameter} olmalıdır");
                    return false;
                }
                break;
                
            case 'max_value':
                if ($value > $parameter) {
                    $this->addError($field, "Bu alan en fazla {$parameter} olmalıdır");
                    return false;
                }
                break;
                
            case 'in':
                if (!in_array($value, $parameter)) {
                    $this->addError($field, 'Geçersiz değer');
                    return false;
                }
                break;
                
            case 'regex':
                if (!preg_match($parameter, $value)) {
                    $this->addError($field, 'Geçersiz format');
                    return false;
                }
                break;
                
            case 'json':
                json_decode($value);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->addError($field, 'Geçerli bir JSON olmalıdır');
                    return false;
                }
                break;
        }
        
        return true;
    }
    
    /**
     * Hata ekle
     * 
     * @param string $field
     * @param string $message
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Hataları getir
     * 
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * İlk hatayı getir
     * 
     * @return string|null
     */
    public function getFirstError() {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }
        return null;
    }
    
    /**
     * Veriyi sanitize et
     * 
     * @param mixed $data
     * @param string $type
     * @return mixed
     */
    public function sanitize($data, $type = 'string') {
        switch ($type) {
            case 'string':
                return $this->sanitizeString($data);
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
            case 'int':
                return (int)$data;
            case 'float':
                return (float)$data;
            case 'boolean':
                return (bool)$data;
            case 'html':
                return $this->sanitizeHtml($data);
            case 'json':
                return json_decode($data, true);
            default:
                return $data;
        }
    }
    
    /**
     * String sanitize et
     * 
     * @param string $data
     * @return string
     */
    private function sanitizeString($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
    
    /**
     * HTML sanitize et
     * 
     * @param string $html
     * @return string
     */
    private function sanitizeHtml($html) {
        // Sadece temiz HTML tag'lerine izin ver
        $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><a><img><div><span>';
        return strip_tags($html, $allowedTags);
    }
    
    /**
     * Tüm veriyi sanitize et
     * 
     * @param array $data
     * @param array $rules
     * @return array
     */
    public function sanitizeAll($data, $rules = []) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $type = $rules[$key] ?? 'string';
            $sanitized[$key] = $this->sanitize($value, $type);
        }
        
        return $sanitized;
    }
    
    /**
     * XSS koruması
     * 
     * @param string $data
     * @return string
     */
    public function xssClean($data) {
        // Fix &entity\n;
        $data = str_replace(['&amp;', '&lt;', '&gt;'], ['&amp;amp;', '&amp;lt;', '&amp;gt;'], $data);
        $data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
        $data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
        $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');
        
        // Remove any attribute starting with "on" or xmlns
        $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);
        
        // Remove javascript: and vbscript: protocols
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
        
        return $data;
    }
}