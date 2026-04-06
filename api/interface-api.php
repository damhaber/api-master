<?php
/**
 * API Master Module - API Interface for Masal Panel
 * 
 * Tüm API provider'ları için ortak interface
 * Bu interface'i implement eden her provider sınıfı aynı metodları içermelidir
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

interface APIMaster_APIInterface
{
    /**
     * Constructor - Provider'ı başlat
     * Parametresiz - Config dosyasından yükler
     */
    public function __construct();
    
    /**
     * API anahtarını ayarla
     * 
     * @param string $apiKey API anahtarı
     * @return bool Başarılı mı?
     */
    public function setApiKey($apiKey);
    
    /**
     * Model adını ayarla
     * 
     * @param string $model Model adı
     * @return bool Başarılı mı?
     */
    public function setModel($model);
    
    /**
     * Mevcut model adını getir
     * 
     * @return string Model adı
     */
    public function getModel();
    
    /**
     * Genel API isteği gönder (joker endpoint)
     * 
     * @param string $endpoint API endpoint
     * @param array $params İstek parametreleri (method, data, vs.)
     * @return array Yanıt
     */
    public function complete($endpoint, $params = []);
    
    /**
     * Streaming istek gönder
     * 
     * @param string $endpoint API endpoint
     * @param callable $callback Her chunk için çağrılacak callback
     * @return bool Başarılı mı?
     */
    public function stream($endpoint, $callback);
    
    /**
     * Desteklenen modelleri getir
     * 
     * @return array Model listesi [['id' => 'model_id', 'name' => 'Model Name', 'enabled' => true]]
     */
    public function getModels();
    
    /**
     * Provider yeteneklerini getir
     * 
     * @return array Yetenekler (chat, vision, streaming, max_tokens, vs.)
     */
    public function getCapabilities();
    
    /**
     * Provider sağlık kontrolü
     * 
     * @return array ['status' => 'healthy|error|warning', 'message' => string, 'response_time_ms' => int]
     */
    public function checkHealth();
    
    /**
     * Chat isteği gönder (basit kullanım için)
     * 
     * @param string $message Kullanıcı mesajı
     * @param array $context Bağlam (system, history, temperature, vs.)
     * @return array Yanıt
     */
    public function chat($message, $context = []);
    
    /**
     * API yanıtından metin çıkar
     * 
     * @param array|string $response API yanıtı
     * @return string Çıkarılan metin
     */
    public function extractText($response);
}