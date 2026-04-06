<?php
/**
 * Helper Fonksiyon Testleri
 * 
 * @package APIMaster
 * @since 1.0.0
 */

class APIMaster_HelperTest {
    private $passed = 0;
    private $failed = 0;
    
    public function run() {
        echo "\n🛠️ HELPER FONKSİYON TESTLERİ\n";
        echo "═══════════════════════════════════════════════════════════\n";
        
        $this->testArrayHelpers();
        $this->testStringHelpers();
        $this->testURLHelpers();
        $this->testDateHelpers();
        $this->testValidationHelpers();
        $this->testFileHelpers();
        
        $this->summary();
    }
    
    private function testArrayHelpers() {
        echo "\n📦 Array Helper Testleri:\n";
        
        // array_get helper test
        $testArray = ['a' => ['b' => ['c' => 'value']]];
        $result = $this->arrayGet($testArray, 'a.b.c', 'default');
        $this->assert($result === 'value', "array_get: nested array erişimi");
        
        $result = $this->arrayGet($testArray, 'x.y.z', 'default');
        $this->assert($result === 'default', "array_get: varsayılan değer");
        
        // array_only helper test
        $testArray = ['name' => 'test', 'age' => 25, 'email' => 'test@test.com'];
        $result = $this->arrayOnly($testArray, ['name', 'email']);
        $this->assert(count($result) === 2, "array_only: doğru sayıda eleman");
        $this->assert(!isset($result['age']), "array_only: istenmeyen eleman filtrelendi");
        
        // array_except helper test
        $result = $this->arrayExcept($testArray, ['age']);
        $this->assert(!isset($result['age']), "array_except: eleman çıkarıldı");
    }
    
    private function testStringHelpers() {
        echo "\n📝 String Helper Testleri:\n";
        
        // str_limit test
        $longString = "Bu çok uzun bir test stringidir ve kesilmesi gerekiyor";
        $result = $this->strLimit($longString, 20);
        $this->assert(strlen($result) <= 23, "str_limit: karakter limiti çalışıyor");
        
        // camel_case test
        $result = $this->camelCase('test_string_convert');
        $this->assert($result === 'testStringConvert', "camel_case: dönüşüm başarılı");
        
        // snake_case test
        $result = $this->snakeCase('testStringConvert');
        $this->assert($result === 'test_string_convert', "snake_case: dönüşüm başarılı");
        
        // random_string test
        $random = $this->randomString(32);
        $this->assert(strlen($random) === 32, "random_string: doğru uzunluk");
        
        // starts_with test
        $this->assert($this->startsWith('hello world', 'hello'), "starts_with: true döndü");
        $this->assert(!$this->startsWith('hello world', 'world'), "starts_with: false döndü");
    }
    
    private function testURLHelpers() {
        echo "\n🔗 URL Helper Testleri:\n";
        
        // url_encode test
        $url = "https://example.com/?q=test value&lang=tr";
        $encoded = $this->urlEncode($url);
        $this->assert(strpos($encoded, ' ') === false, "url_encode: boşluklar encode edildi");
        
        // build_query test
        $params = ['page' => 1, 'limit' => 10, 'sort' => 'desc'];
        $query = $this->buildQuery($params);
        $this->assert(strpos($query, 'page=1') !== false, "build_query: parametreler eklendi");
        
        // current_url test (mock)
        $_SERVER['REQUEST_URI'] = '/test/path?param=value';
        $currentUrl = $this->currentUrl();
        $this->assert(!empty($currentUrl), "current_url: geçerli URL alındı");
    }
    
    private function testDateHelpers() {
        echo "\n📅 Date Helper Testleri:\n";
        
        // time_ago test
        $timestamp = time() - 3600; // 1 saat önce
        $result = $this->timeAgo($timestamp);
        $this->assert(strpos($result, 'saat') !== false, "time_ago: geçmiş zaman hesaplama");
        
        // format_date test
        $result = $this->formatDate($timestamp, 'Y-m-d');
        $this->assert(strlen($result) === 10, "format_date: tarih formatlama");
        
        // is_valid_date test
        $this->assert($this->isValidDate('2024-01-01'), "is_valid_date: geçerli tarih");
        $this->assert(!$this->isValidDate('2024-13-45'), "is_valid_date: geçersiz tarih");
    }
    
    private function testValidationHelpers() {
        echo "\n✓ Validation Helper Testleri:\n";
        
        // is_email test
        $this->assert($this->isEmail('test@example.com'), "is_email: geçerli email");
        $this->assert(!$this->isEmail('invalid-email'), "is_email: geçersiz email");
        
        // is_url test
        $this->assert($this->isUrl('https://example.com'), "is_url: geçerli URL");
        $this->assert(!$this->isUrl('not-a-url'), "is_url: geçersiz URL");
        
        // is_json test
        $this->assert($this->isJson('{"key":"value"}'), "is_json: geçerli JSON");
        $this->assert(!$this->isJson('not json'), "is_json: geçersiz JSON");
        
        // is_phone test
        $this->assert($this->isPhone('+905551234567'), "is_phone: geçerli telefon");
    }
    
    private function testFileHelpers() {
        echo "\n📁 File Helper Testleri:\n";
        
        $testDir = APIMASTER_TEST_OUTPUT . '/helper_test';
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        
        // file_size test
        $testFile = $testDir . '/test.txt';
        file_put_contents($testFile, str_repeat('a', 1024)); // 1KB
        
        $size = $this->fileSize($testFile);
        $this->assert($size === '1 KB', "file_size: boyut hesaplama");
        
        // create_directory test
        $newDir = $testDir . '/new_folder';
        $this->createDirectory($newDir);
        $this->assert(is_dir($newDir), "create_directory: klasör oluşturma");
        
        // clean_filename test
        $filename = $this->cleanFilename("Test File/Name: With*Special?Chars.txt");
        $this->assert(strpos($filename, '/') === false, "clean_filename: özel karakterler temizlendi");
        
        // Temizlik
        $this->deleteDirectory($testDir);
        $this->assert(!is_dir($testDir), "delete_directory: klasör silme");
    }
    
    // Helper metotlar (gerçek implementasyonlar)
    private function arrayGet($array, $key, $default = null) {
        if (is_null($key)) return $array;
        if (isset($array[$key])) return $array[$key];
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        return $array;
    }
    
    private function arrayOnly($array, $keys) {
        return array_intersect_key($array, array_flip((array) $keys));
    }
    
    private function arrayExcept($array, $keys) {
        return array_diff_key($array, array_flip((array) $keys));
    }
    
    private function strLimit($value, $limit = 100, $end = '...') {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }
        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }
    
    private function camelCase($value) {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));
        return lcfirst(str_replace(' ', '', $value));
    }
    
    private function snakeCase($value) {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
    
    private function randomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    private function startsWith($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
    
    private function urlEncode($url) {
        return urlencode($url);
    }
    
    private function buildQuery($params) {
        return http_build_query($params);
    }
    
    private function currentUrl() {
        return (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . 
               $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    
    private function timeAgo($timestamp) {
        $diff = time() - $timestamp;
        if ($diff < 60) return 'az önce';
        if ($diff < 3600) return floor($diff / 60) . ' dakika önce';
        if ($diff < 86400) return floor($diff / 3600) . ' saat önce';
        return floor($diff / 86400) . ' gün önce';
    }
    
    private function formatDate($timestamp, $format) {
        return date($format, $timestamp);
    }
    
    private function isValidDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    private function isEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    private function isUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    private function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    private function isPhone($phone) {
        return preg_match('/^[\+][0-9]{10,15}$/', $phone);
    }
    
    private function fileSize($file) {
        $bytes = filesize($file);
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    private function createDirectory($path) {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        return is_dir($path);
    }
    
    private function cleanFilename($filename) {
        return preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    }
    
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        return rmdir($dir);
    }
    
    private function assert($condition, $message) {
        if ($condition) {
            $this->passed++;
            echo "  ✅ {$message}\n";
        } else {
            $this->failed++;
            echo "  ❌ {$message}\n";
        }
    }
    
    private function summary() {
        $total = $this->passed + $this->failed;
        echo "\n═══════════════════════════════════════════════════════════\n";
        echo "📊 Helper Test Özeti: {$this->passed}/{$total} başarılı\n";
    }
}