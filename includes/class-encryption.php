<?php
/**
 * API Master - Encryption Manager
 * 
 * @package APIMaster
 * @subpackage Includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class APIMaster_Encryption
 * 
 * Şifreleme ve hash yönetimi
 * WordPress fonksiyonları KULLANILMAZ!
 * SADECE curl ve native PHP fonksiyonları
 */
class APIMaster_Encryption {
    
    /**
     * @var string Şifreleme anahtarı
     */
    private $encryptionKey;
    
    /**
     * @var string Şifreleme metodu
     */
    private $cipherMethod = 'aes-256-gcm';
    
    /**
     * @var string Hash algoritması
     */
    private $hashAlgorithm = 'sha256';
    
    /**
     * @var int Hash iteration count
     */
    private $hashIterations = 10000;
    
    /**
     * Constructor
     * 
     * @param string|null $encryptionKey Özel şifreleme anahtarı (opsiyonel)
     */
    public function __construct($encryptionKey = null) {
        if ($encryptionKey) {
            $this->encryptionKey = $encryptionKey;
        } else {
            $this->encryptionKey = $this->getOrCreateMasterKey();
        }
    }
    
    /**
     * Master key'i al veya oluştur
     * 
     * @return string
     */
    private function getOrCreateMasterKey() {
        $keyFile = dirname(dirname(__FILE__)) . '/config/master.key';
        
        if (file_exists($keyFile)) {
            return trim(file_get_contents($keyFile));
        }
        
        // Yeni master key oluştur
        $key = $this->generateSecureKey(32);
        
        // Key'i güvenli şekilde kaydet
        $keyDir = dirname($keyFile);
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0700, true);
        }
        
        file_put_contents($keyFile, $key);
        chmod($keyFile, 0600);
        
        return $key;
    }
    
    /**
     * Güvenli rastgele anahtar oluştur
     * 
     * @param int $length Byte cinsinden uzunluk
     * @return string
     */
    public function generateSecureKey($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * AES-256-GCM ile veri şifrele
     * 
     * @param string $data Şifrelenecek veri
     * @param string|null $key Özel anahtar (opsiyonel)
     * @return string|false Base64 encoded encrypted data
     */
    public function encrypt($data, $key = null) {
        if (empty($data)) {
            return false;
        }
        
        $useKey = $key ?? $this->encryptionKey;
        $iv = random_bytes(openssl_cipher_iv_length($this->cipherMethod));
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $data,
            $this->cipherMethod,
            hex2bin($useKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );
        
        if ($encrypted === false) {
            return false;
        }
        
        // IV, Tag ve Encrypted data'yı birleştir
        $combined = $iv . $tag . $encrypted;
        
        return base64_encode($combined);
    }
    
    /**
     * AES-256-GCM ile veri deşifrele
     * 
     * @param string $encryptedData Base64 encoded encrypted data
     * @param string|null $key Özel anahtar (opsiyonel)
     * @return string|false
     */
    public function decrypt($encryptedData, $key = null) {
        if (empty($encryptedData)) {
            return false;
        }
        
        $useKey = $key ?? $this->encryptionKey;
        $decoded = base64_decode($encryptedData);
        
        $ivLength = openssl_cipher_iv_length($this->cipherMethod);
        $tagLength = 16;
        
        $iv = substr($decoded, 0, $ivLength);
        $tag = substr($decoded, $ivLength, $tagLength);
        $ciphertext = substr($decoded, $ivLength + $tagLength);
        
        $decrypted = openssl_decrypt(
            $ciphertext,
            $this->cipherMethod,
            hex2bin($useKey),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        return $decrypted;
    }
    
    /**
     * API Key hash oluştur (depolama için)
     * 
     * @param string $apiKey
     * @return string
     */
    public function hashApiKey($apiKey) {
        $salt = $this->generateSecureKey(16);
        $hash = hash_pbkdf2(
            $this->hashAlgorithm,
            $apiKey,
            hex2bin($salt),
            $this->hashIterations,
            32,
            true
        );
        
        return base64_encode($salt . ':' . $hash);
    }
    
    /**
     * API Key hash doğrula
     * 
     * @param string $apiKey
     * @param string $storedHash
     * @return bool
     */
    public function verifyApiKey($apiKey, $storedHash) {
        $decoded = base64_decode($storedHash);
        $parts = explode(':', $decoded, 2);
        
        if (count($parts) !== 2) {
            return false;
        }
        
        $salt = $parts[0];
        $hash = $parts[1];
        
        $computedHash = hash_pbkdf2(
            $this->hashAlgorithm,
            $apiKey,
            $salt,
            $this->hashIterations,
            32,
            true
        );
        
        return hash_equals($hash, $computedHash);
    }
    
    /**
     * Basit hash (hızlı doğrulama için)
     * 
     * @param string $data
     * @return string
     */
    public function simpleHash($data) {
        return hash($this->hashAlgorithm, $data);
    }
    
    /**
     * HMAC oluştur
     * 
     * @param string $data
     * @param string|null $key
     * @return string
     */
    public function hmac($data, $key = null) {
        $useKey = $key ?? $this->encryptionKey;
        return hash_hmac($this->hashAlgorithm, $data, hex2bin($useKey));
    }
    
    /**
     * HMAC doğrula
     * 
     * @param string $data
     * @param string $hmac
     * @param string|null $key
     * @return bool
     */
    public function verifyHmac($data, $hmac, $key = null) {
        $expected = $this->hmac($data, $key);
        return hash_equals($expected, $hmac);
    }
    
    /**
     * JWT Token oluştur
     * 
     * @param array $payload
     * @param int $expiry Seconds from now
     * @param string|null $secret
     * @return string
     */
    public function generateJwt($payload, $expiry = 3600, $secret = null) {
        $useSecret = $secret ?? $this->encryptionKey;
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry;
        $payloadJson = json_encode($payload);
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payloadJson));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, hex2bin($useSecret), true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }
    
    /**
     * JWT Token doğrula ve decode et
     * 
     * @param string $jwt
     * @param string|null $secret
     * @return array|false
     */
    public function verifyJwt($jwt, $secret = null) {
        $useSecret = $secret ?? $this->encryptionKey;
        
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }
        
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
        
        $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64UrlSignature));
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, hex2bin($useSecret), true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64UrlPayload)), true);
        
        // Expiry kontrolü
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Rastgele token oluştur
     * 
     * @param int $length
     * @return string
     */
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * UUID v4 oluştur
     * 
     * @return string
     */
    public function generateUuid() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Veri imzala
     * 
     * @param string $data
     * @param string|null $key
     * @return string
     */
    public function sign($data, $key = null) {
        $useKey = $key ?? $this->encryptionKey;
        return $this->hmac($data, $useKey);
    }
    
    /**
     * İmza doğrula
     * 
     * @param string $data
     * @param string $signature
     * @param string|null $key
     * @return bool
     */
    public function verifySignature($data, $signature, $key = null) {
        return $this->verifyHmac($data, $signature, $key);
    }
    
    /**
     * Şifreleme metodunu değiştir
     * 
     * @param string $method
     * @return bool
     */
    public function setCipherMethod($method) {
        $availableMethods = openssl_get_cipher_methods();
        if (in_array($method, $availableMethods)) {
            $this->cipherMethod = $method;
            return true;
        }
        return false;
    }
    
    /**
     * Hash algoritmasını değiştir
     * 
     * @param string $algorithm
     * @return bool
     */
    public function setHashAlgorithm($algorithm) {
        if (in_array($algorithm, hash_algos())) {
            $this->hashAlgorithm = $algorithm;
            return true;
        }
        return false;
    }
    
    /**
     * Mevcut şifreleme metodunu getir
     * 
     * @return string
     */
    public function getCipherMethod() {
        return $this->cipherMethod;
    }
    
    /**
     * Mevcut hash algoritmasını getir
     * 
     * @return string
     */
    public function getHashAlgorithm() {
        return $this->hashAlgorithm;
    }
    
    /**
     * Sistem kontrolü - Gerekli extension'lar var mı?
     * 
     * @return array
     */
    public static function checkRequirements() {
        return [
            'openssl' => extension_loaded('openssl'),
            'hash' => extension_loaded('hash'),
            'random' => function_exists('random_bytes')
        ];
    }
}