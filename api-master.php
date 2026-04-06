<?php
/**
 * API Master Module for Masal Panel
 * BU BİR WORDPRESS MODÜLÜDÜR, PLUGIN DEĞİLDİR
 * 
 * @package APIMaster
 * @version 3.0.0
 */

// WordPress müdahalesini engelle - modül bağımsız çalışır
if (!defined('ABSPATH')) {
    // Normal PHP çalışması - sorun yok
}

// Modül dizinini tanımla
if (!defined('API_MASTER_MODULE_DIR')) {
    define('API_MASTER_MODULE_DIR', __DIR__);
}
if (!defined('API_MASTER_CONFIG_DIR')) {
    define('API_MASTER_CONFIG_DIR', API_MASTER_MODULE_DIR . '/config');
}
if (!defined('API_MASTER_DATA_DIR')) {
    define('API_MASTER_DATA_DIR', API_MASTER_MODULE_DIR . '/data');
}
if (!defined('API_MASTER_LOG_DIR')) {
    define('API_MASTER_LOG_DIR', API_MASTER_MODULE_DIR . '/logs');
}
if (!defined('API_MASTER_CACHE_DIR')) {
    define('API_MASTER_CACHE_DIR', API_MASTER_MODULE_DIR . '/cache');
}

/**
 * API Master Autoloader - WordPress'siz çalışır
 */
spl_autoload_register(function($className) {
    // Sadece APIMaster_ prefix'li sınıfları yükle
    if (strpos($className, 'APIMaster_') !== 0) {
        return;
    }
    
    // Prefix'i kaldır
    $className = substr($className, 10);
    
    // Klasör ve dosya yolları
    $paths = [
        API_MASTER_MODULE_DIR . '/includes/',
        API_MASTER_MODULE_DIR . '/security/',
        API_MASTER_MODULE_DIR . '/middleware/',
        API_MASTER_MODULE_DIR . '/core/',
        API_MASTER_MODULE_DIR . '/api/',
        API_MASTER_MODULE_DIR . '/ajax/',
        API_MASTER_MODULE_DIR . '/cron/',
        API_MASTER_MODULE_DIR . '/queue/',
        API_MASTER_MODULE_DIR . '/learning/',
        API_MASTER_MODULE_DIR . '/vector/',
        API_MASTER_MODULE_DIR . '/cache/'
    ];
    
    // Snake_case'ye çevir (Class_Name -> class-name.php)
    $fileName = 'class-' . str_replace('_', '-', strtolower($className)) . '.php';
    
    foreach ($paths as $path) {
        $fullPath = $path . $fileName;
        if (file_exists($fullPath)) {
            require_once $fullPath;
            return;
        }
    }
});

/**
 * API Master Module Class - WordPress hook'ları içermez
 */
class APIMaster_Module {
    
    private static $instance = null;
    private $loadedClasses = [];
    
    private function __construct() {
        $this->init();
    }
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function init() {
        $this->init_core_classes();
    }
    
    private function init_core_classes() {
        // Core sınıfları yükle
        $coreClasses = [
            'APIMaster_Constants',
            'APIMaster_Encryption',
            'APIMaster_Database',
            'APIMaster_Logger',
            'APIMaster_Cache',
            'APIMaster_Core'
        ];
        
        foreach ($coreClasses as $class) {
            $this->load_class($class);
        }
    }
    
    private function load_class($className) {
        if (class_exists($className)) {
            $this->loadedClasses[$className] = new $className();
            return $this->loadedClasses[$className];
        }
        return null;
    }
    
    public function get($className) {
        return $this->loadedClasses[$className] ?? null;
    }
    
    public function get_loaded_classes() {
        return array_keys($this->loadedClasses);
    }
}

// Modülü başlat
function api_master_module() {
    return APIMaster_Module::get_instance();
}

// Başlat
api_master_module();