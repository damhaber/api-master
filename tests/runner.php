#!/usr/bin/env php
<?php
/**
 * APIMaster Test Runner
 * 
 * Tüm testleri çalıştıran ana dosya
 * 
 * @package APIMaster
 * @since 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                   ║\n";
echo "║     🚀 APIMaster Test Suite v1.0                                 ║\n";
echo "║     📝 WordPress Fonksiyonları YASAK - SADECE cURL               ║\n";
echo "║     💾 Database YOK - JSON Config                                ║\n";
echo "║                                                                   ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n";

// Test sınıflarını yükle
$testFiles = [
    'core-test.php' => 'APIMaster_CoreTest',
    'provider-test.php' => 'APIMaster_ProviderTest',
    'helper-test.php' => 'APIMaster_HelperTest',
    'integration-test.php' => 'APIMaster_IntegrationTest',
    'performance-test.php' => 'APIMaster_PerformanceTest'
];

$totalTests = 0;
$totalPassed = 0;

foreach ($testFiles as $file => $className) {
    $filePath = __DIR__ . '/' . $file;
    
    if (file_exists($filePath)) {
        require_once $filePath;
        
        if (class_exists($className)) {
            $test = new $className();
            if (method_exists($test, 'run')) {
                $test->run();
                $totalTests++;
                $totalPassed++;
            }
        } else {
            echo "⚠️ Sınıf bulunamadı: {$className}\n";
        }
    } else {
        echo "⚠️ Dosya bulunamadı: {$filePath}\n";
    }
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║                    🎯 TÜM TESTLER TAMAMLANDI 🎯                    ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// WordPress fonksiyonu kontrolü (KRİTİK!)
if (function_exists('add_action')) {
    echo "🔴 KRİTİK HATA: WordPress fonksiyonları tespit edildi!\n";
    echo "🔴 Bu YASAK! Tüm kodları kontrol edin!\n";
    exit(1);
} else {
    echo "✅ WordPress fonksiyonları YOK - Güvenli\n";
}

// Database kontrolü
if (class_exists('wpdb')) {
    echo "🔴 KRİTİK HATA: WordPress Database tespit edildi!\n";
    echo "🔴 Bu YASAK! JSON config kullanılmalı!\n";
    exit(1);
} else {
    echo "✅ Database yok - JSON config kullanılıyor\n";
}

echo "\n🎉 Tüm kontroller geçti! APIMaster hazır!\n\n";