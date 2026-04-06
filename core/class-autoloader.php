<?php
if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Autoloader
{
    private $prefixes = [];
    private $classMap = [];

    public function __construct()
    {
        $this->registerNamespaces();
        $this->buildClassMap();
    }

    private function registerNamespaces()
    {
        $moduleDir = dirname(__DIR__, 1) . '/';
        
        $this->addNamespace('APIMaster\\', $moduleDir);
        $this->addNamespace('APIMaster\\API\\', $moduleDir . 'api/');
        $this->addNamespace('APIMaster\\Core\\', $moduleDir . 'core/');
        $this->addNamespace('APIMaster\\Learning\\', $moduleDir . 'learning/');
        $this->addNamespace('APIMaster\\Vector\\', $moduleDir . 'vector/');
        $this->addNamespace('APIMaster\\Security\\', $moduleDir . 'security/');
        $this->addNamespace('APIMaster\\UI\\', $moduleDir . 'ui/');
        $this->addNamespace('APIMaster\\Includes\\', $moduleDir . 'includes/');
        $this->addNamespace('APIMaster\\Middleware\\', $moduleDir . 'middleware/');
        $this->addNamespace('APIMaster\\Queue\\', $moduleDir . 'queue/');
        $this->addNamespace('APIMaster\\Cron\\', $moduleDir . 'cron/');
    }

    public function register()
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    public function unregister()
    {
        spl_autoload_unregister([$this, 'loadClass']);
    }

    public function addNamespace($prefix, $baseDir, $prepend = false)
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . '/';
        
        if (!isset($this->prefixes[$prefix])) {
            $this->prefixes[$prefix] = [];
        }
        
        if ($prepend) {
            array_unshift($this->prefixes[$prefix], $baseDir);
        } else {
            array_push($this->prefixes[$prefix], $baseDir);
        }
    }

    private function buildClassMap()
    {
        $moduleDir = dirname(__DIR__, 1) . '/';
        
        $this->classMap = [
            // Core sınıflar
            'APIMaster_Autoloader' => $moduleDir . 'core/class-autoloader.php',
            'APIMaster_Core' => $moduleDir . 'core/class-core.php',
            'APIMaster_Activator' => $moduleDir . 'core/class-activator.php',
            'APIMaster_Deactivator' => $moduleDir . 'core/class-deactivator.php',
            'APIMaster_Logger' => $moduleDir . 'core/class-logger.php',
            'APIMaster_Cache' => $moduleDir . 'core/class-cache.php',
            'APIMaster_RateLimiter' => $moduleDir . 'core/class-rate-limiter.php',
            'APIMaster_Validator' => $moduleDir . 'core/class-validator.php',
            
            // API sınıflar - Temel
            'APIMaster_API_Factory' => $moduleDir . 'api/class-api-factory.php',
            
            // API Provider'lar - Ana AI/ML
            'APIMaster_OpenAI' => $moduleDir . 'api/class-openai.php',
            'APIMaster_DeepSeek' => $moduleDir . 'api/class-deepseek.php',
            'APIMaster_Gemini' => $moduleDir . 'api/class-gemini.php',
            'APIMaster_Claude' => $moduleDir . 'api/class-claude.php',
            'APIMaster_Cohere' => $moduleDir . 'api/class-cohere.php',
            'APIMaster_Mistral' => $moduleDir . 'api/class-mistral.php',
            
            // API Provider'lar - Diğer servisler
            'APIMaster_StabilityAI' => $moduleDir . 'api/class-stabilityai.php',
            'APIMaster_D_ID' => $moduleDir . 'api/class-d-id.php',
            'APIMaster_HeyGen' => $moduleDir . 'api/class-heygen.php',
            'APIMaster_GitLab' => $moduleDir . 'api/class-gitlab.php',
            'APIMaster_Bitbucket' => $moduleDir . 'api/class-bitbucket.php',
            'APIMaster_HubSpot' => $moduleDir . 'api/class-hubspot.php',
            'APIMaster_Salesforce' => $moduleDir . 'api/class-salesforce.php',
            'APIMaster_Zapier' => $moduleDir . 'api/class-zapier.php',
            'APIMaster_Make' => $moduleDir . 'api/class-make.php',
            'APIMaster_Pabbly' => $moduleDir . 'api/class-pabbly.php',
            
            // Learning sınıflar
            'APIMaster_Learning_Manager' => $moduleDir . 'learning/class-learning-manager.php',
            'APIMaster_Performance_Tracker' => $moduleDir . 'learning/class-performance-tracker.php',
            'APIMaster_Intent_Analyzer' => $moduleDir . 'learning/class-intent-analyzer.php',
            'APIMaster_User_Profiler' => $moduleDir . 'learning/class-user-profiler.php',
            'APIMaster_Adaptive_Router' => $moduleDir . 'learning/class-adaptive-router.php',
            'APIMaster_Feedback_Loop' => $moduleDir . 'learning/class-feedback-loop.php',
            
            // Vector sınıflar
            'APIMaster_Vector_Store' => $moduleDir . 'vector/class-vector-store.php',
            'APIMaster_Embedding_Generator' => $moduleDir . 'vector/class-embedding-generator.php',
            'APIMaster_Similarity_Search' => $moduleDir . 'vector/class-similarity-search.php',
            
            // Security sınıflar
            'APIMaster_API_Key_Manager' => $moduleDir . 'security/class-api-key-manager.php',
            'APIMaster_Security_Audit' => $moduleDir . 'security/class-security-audit.php',
            'APIMaster_RateLimiter_Security' => $moduleDir . 'security/class-rate-limiter.php',
            
            // Includes sınıflar
            'APIMaster_Encryption' => $moduleDir . 'includes/class-encryption.php',
            'APIMaster_Constants' => $moduleDir . 'includes/class-constants.php'
        ];
        
        $this->scanApiProviders();
    }

    private function scanApiProviders()
    {
        $apiDir = dirname(__DIR__, 1) . '/api/';
        
        if (!is_dir($apiDir)) {
            return;
        }
        
        $files = glob($apiDir . 'class-*.php');
        
        foreach ($files as $file) {
            $filename = basename($file, '.php');
            $classname = 'APIMaster_' . str_replace('class-', '', $filename);
            $classname = str_replace('-', '_', $classname);
            
            if (!isset($this->classMap[$classname])) {
                $this->classMap[$classname] = $file;
            }
        }
        
        $apiFiles = [
            'class-api-factory.php' => 'APIMaster_API_Factory'
        ];
        
        foreach ($apiFiles as $fileName => $className) {
            $filePath = $apiDir . $fileName;
            if (file_exists($filePath) && !isset($this->classMap[$className])) {
                $this->classMap[$className] = $filePath;
            }
        }
    }

    public function loadClass($class)
    {
        if (isset($this->classMap[$class])) {
            $file = $this->classMap[$class];
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
        
        $prefix = $class;
        
        while (false !== ($pos = strrpos($prefix, '\\'))) {
            $prefix = substr($class, 0, $pos + 1);
            $relativeClass = substr($class, $pos + 1);
            
            if ($this->loadMappedFile($prefix, $relativeClass)) {
                return true;
            }
            
            $prefix = rtrim($prefix, '\\');
        }
        
        return false;
    }

    private function loadMappedFile($prefix, $relativeClass)
    {
        if (!isset($this->prefixes[$prefix])) {
            return false;
        }
        
        foreach ($this->prefixes[$prefix] as $baseDir) {
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            
            $alternatives = [
                $file,
                $baseDir . 'class-' . str_replace('_', '-', strtolower($relativeClass)) . '.php',
                $baseDir . strtolower(str_replace('_', '-', $relativeClass)) . '.php',
            ];
            
            foreach ($alternatives as $altFile) {
                if (file_exists($altFile)) {
                    require_once $altFile;
                    return true;
                }
            }
        }
        
        return false;
    }

    public function addClassMap($class, $file)
    {
        $this->classMap[$class] = $file;
    }

    public function removeClassMap($class)
    {
        unset($this->classMap[$class]);
    }

    public function getClassMap()
    {
        return $this->classMap;
    }

    public function findClassFile($class)
    {
        if (isset($this->classMap[$class])) {
            return $this->classMap[$class];
        }
        
        $prefix = $class;
        
        while (false !== ($pos = strrpos($prefix, '\\'))) {
            $prefix = substr($class, 0, $pos + 1);
            $relativeClass = substr($class, $pos + 1);
            
            if (isset($this->prefixes[$prefix])) {
                foreach ($this->prefixes[$prefix] as $baseDir) {
                    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
                    if (file_exists($file)) {
                        return $file;
                    }
                }
            }
        }
        
        return null;
    }

    public function isRegistered()
    {
        $functions = spl_autoload_functions();
        
        foreach ($functions as $function) {
            if (is_array($function) && $function[0] === $this && $function[1] === 'loadClass') {
                return true;
            }
        }
        
        return false;
    }
}

function api_master_autoload($class)
{
    static $autoloader = null;
    
    if (null === $autoloader) {
        $autoloader = new APIMaster_Autoloader();
        $autoloader->register();
    }
    
    return $autoloader->loadClass($class);
}

if (!class_exists('APIMaster_Autoloader')) {
    spl_autoload_register('api_master_autoload');
}