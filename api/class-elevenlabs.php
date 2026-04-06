<?php
/**
 * API Master Module - ElevenLabs API
 * Text-to-Speech ve Voice Generation için ElevenLabs API
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_ElevenLabs implements APIMaster_APIInterface {
    
    /**
     * API base URL
     * @var string
     */
    private $base_url = 'https://api.elevenlabs.io/v1';
    
    /**
     * API anahtarı
     * @var string
     */
    private $api_key;
    
    /**
     * Yapılandırma
     * @var array
     */
    private $config = [];
    
    /**
     * Mevcut sesler
     * @var array|null
     */
    private $voices = null;
    
    /**
     * Constructor
     * 
     * @param array $config Yapılandırma ayarları
     */
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'api_key' => '',
            'default_voice' => '21m00Tcm4TlvDq8ikWAM', // Rachel
            'default_model' => 'eleven_monolingual_v1',
            'timeout' => 60,
            'max_retries' => 3,
            'default_stability' => 0.5,
            'default_similarity_boost' => 0.75
        ], $config);
        
        $this->api_key = $this->config['api_key'];
    }
    
    /**
     * API isteği gönder
     * 
     * @param string $endpoint Endpoint
     * @param array $params Parametreler
     * @param string $method HTTP metodu
     * @return array API yanıtı
     */
    public function request($endpoint, $params = [], $method = 'GET') {
        $url = $this->base_url . $endpoint;
        $headers = $this->getHeaders();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => $error,
                'code' => $httpCode
            ];
        }
        
        return $this->parseResponse($response, $httpCode);
    }
    
    /**
     * Yanıtı parse et
     * 
     * @param string $response Ham yanıt
     * @param int $httpCode HTTP kodu
     * @return array Parse edilmiş yanıt
     */
    private function parseResponse($response, $httpCode) {
        if ($httpCode === 200 || $httpCode === 201) {
            // Audio response (binary)
            if (strpos($response, '{') === false && strpos($response, '[') === false) {
                return [
                    'success' => true,
                    'audio' => base64_encode($response),
                    'audio_data' => $response,
                    'content_type' => 'audio/mpeg'
                ];
            }
            
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'success' => true,
                    'data' => $data
                ];
            }
            
            return [
                'success' => true,
                'audio' => base64_encode($response),
                'audio_data' => $response
            ];
        }
        
        $data = json_decode($response, true);
        
        return [
            'success' => false,
            'error' => $data['detail']['message'] ?? $data['detail'] ?? 'Unknown error',
            'code' => $httpCode,
            'raw_response' => $response
        ];
    }
    
    /**
     * Header'ları oluştur
     * 
     * @return array Header'lar
     */
    private function getHeaders() {
        return [
            'Content-Type: application/json',
            'xi-api-key: ' . $this->api_key
        ];
    }
    
    /**
     * Metni sese çevir
     * 
     * @param string $text Metin
     * @param array $options Seçenekler
     * @return array Ses verisi
     */
    public function textToSpeech($text, $options = []) {
        $options = array_merge([
            'voice_id' => $this->config['default_voice'],
            'model_id' => $this->config['default_model'],
            'stability' => $this->config['default_stability'],
            'similarity_boost' => $this->config['default_similarity_boost'],
            'style' => 0.0,
            'use_speaker_boost' => true,
            'output_format' => 'mp3_44100_128'
        ], $options);
        
        if (empty($text)) {
            return [
                'success' => false,
                'error' => 'Text cannot be empty'
            ];
        }
        
        $endpoint = "/text-to-speech/{$options['voice_id']}";
        
        $params = [
            'text' => $text,
            'model_id' => $options['model_id'],
            'voice_settings' => [
                'stability' => $options['stability'],
                'similarity_boost' => $options['similarity_boost'],
                'style' => $options['style'],
                'use_speaker_boost' => $options['use_speaker_boost']
            ]
        ];
        
        if ($options['output_format']) {
            $endpoint .= "?output_format={$options['output_format']}";
        }
        
        return $this->request($endpoint, $params, 'POST');
    }
    
    /**
     * Metni sese çevir ve stream et
     * 
     * @param string $text Metin
     * @param callable $callback Stream callback
     * @param array $options Seçenekler
     * @return array Sonuç
     */
    public function streamTextToSpeech($text, $callback, $options = []) {
        $options = array_merge([
            'voice_id' => $this->config['default_voice'],
            'model_id' => $this->config['default_model'],
            'stability' => $this->config['default_stability'],
            'similarity_boost' => $this->config['default_similarity_boost']
        ], $options);
        
        $url = $this->base_url . "/text-to-speech/{$options['voice_id']}/stream";
        $headers = $this->getHeaders();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'text' => $text,
            'model_id' => $options['model_id'],
            'voice_settings' => [
                'stability' => $options['stability'],
                'similarity_boost' => $options['similarity_boost']
            ]
        ]));
        
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($callback) {
            if (is_callable($callback)) {
                $callback($data);
            }
            return strlen($data);
        });
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => $error
            ];
        }
        
        return [
            'success' => true,
            'code' => $httpCode
        ];
    }
    
    /**
     * Sesleri listele
     * 
     * @return array Sesler listesi
     */
    public function getVoices() {
        if ($this->voices !== null) {
            return $this->voices;
        }
        
        $result = $this->request('/voices');
        
        if ($result['success'] && isset($result['data']['voices'])) {
            $this->voices = $result['data']['voices'];
            return $this->voices;
        }
        
        return [];
    }
    
    /**
     * Belirli bir sesi getir
     * 
     * @param string $voice_id Ses ID'si
     * @return array Ses bilgileri
     */
    public function getVoice($voice_id) {
        return $this->request("/voices/{$voice_id}");
    }
    
    /**
     * Ses oluştur (Voice design)
     * 
     * @param array $params Ses parametreleri
     * @return array Oluşturulan ses
     */
    public function createVoice($params) {
        $required = ['name', 'labels'];
        
        foreach ($required as $field) {
            if (!isset($params[$field])) {
                return [
                    'success' => false,
                    'error' => "Missing required field: {$field}"
                ];
            }
        }
        
        return $this->request('/voices/add', $params, 'POST');
    }
    
    /**
     * Sesi klonla
     * 
     * @param string $name Ses adı
     * @param array $audio_files Ses dosyaları
     * @param array $options Seçenekler
     * @return array Klonlanan ses
     */
    public function cloneVoice($name, $audio_files, $options = []) {
        $options = array_merge([
            'description' => '',
            'labels' => []
        ], $options);
        
        $boundary = uniqid('', true);
        $body = '';
        
        $body .= $this->addMultipartField('name', $name, $boundary);
        $body .= $this->addMultipartField('description', $options['description'], $boundary);
        
        if (!empty($options['labels'])) {
            $body .= $this->addMultipartField('labels', json_encode($options['labels']), $boundary);
        }
        
        foreach ((array)$audio_files as $index => $file) {
            $field_name = 'files';
            $filename = "audio_{$index}.mp3";
            $content = $this->getFileContent($file);
            
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$field_name}\"; filename=\"{$filename}\"\r\n";
            $body .= "Content-Type: audio/mpeg\r\n\r\n";
            $body .= $content . "\r\n";
        }
        
        $body .= "--{$boundary}--\r\n";
        
        $url = $this->base_url . '/voices/add';
        $headers = [
            'xi-api-key: ' . $this->api_key,
            'Content-Type: multipart/form-data; boundary=' . $boundary
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $this->parseResponse($response, $httpCode);
    }
    
    /**
     * Multipart form field ekle
     * 
     * @param string $name Field adı
     * @param string $value Field değeri
     * @param string $boundary Boundary
     * @return string Field content
     */
    private function addMultipartField($name, $value, $boundary) {
        $content = "--{$boundary}\r\n";
        $content .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
        $content .= "{$value}\r\n";
        return $content;
    }
    
    /**
     * Dosya içeriğini al
     * 
     * @param string $file Dosya yolu veya base64
     * @return string Dosya içeriği
     */
    private function getFileContent($file) {
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        
        if (preg_match('/^data:audio\/\w+;base64,/', $file)) {
            return base64_decode(preg_replace('/^data:audio\/\w+;base64,/', '', $file));
        }
        
        return $file;
    }
    
    /**
     * Sesi sil
     * 
     * @param string $voice_id Ses ID'si
     * @return array Sonuç
     */
    public function deleteVoice($voice_id) {
        return $this->request("/voices/{$voice_id}", [], 'DELETE');
    }
    
    /**
     * Ses ayarlarını düzenle
     * 
     * @param string $voice_id Ses ID'si
     * @param array $settings Yeni ayarlar
     * @return array Güncellenmiş ses
     */
    public function editVoice($voice_id, $settings) {
        return $this->request("/voices/{$voice_id}/edit", $settings, 'POST');
    }
    
    /**
     * Proje oluştur
     * 
     * @param string $name Proje adı
     * @param array $chapters Bölümler
     * @return array Proje bilgileri
     */
    public function createProject($name, $chapters) {
        $params = [
            'name' => $name,
            'default_title' => $name,
            'default_voice_id' => $this->config['default_voice'],
            'default_model_id' => $this->config['default_model']
        ];
        
        $result = $this->request('/projects/add', $params, 'POST');
        
        if ($result['success'] && isset($result['data']['project_id'])) {
            $project_id = $result['data']['project_id'];
            
            foreach ($chapters as $chapter) {
                $this->addChapter($project_id, $chapter['name'], $chapter['text']);
            }
            
            $this->convertProject($project_id);
            $result['data']['chapters'] = $chapters;
        }
        
        return $result;
    }
    
    /**
     * Projeye bölüm ekle
     * 
     * @param string $project_id Proje ID'si
     * @param string $name Bölüm adı
     * @param string $text Bölüm metni
     * @return array Bölüm bilgileri
     */
    public function addChapter($project_id, $name, $text) {
        $params = [
            'name' => $name,
            'text' => $text
        ];
        
        return $this->request("/projects/{$project_id}/chapters/add", $params, 'POST');
    }
    
    /**
     * Projeyi sese çevir
     * 
     * @param string $project_id Proje ID'si
     * @return array Sonuç
     */
    public function convertProject($project_id) {
        return $this->request("/projects/{$project_id}/convert", [], 'POST');
    }
    
    /**
     * Projeyi getir
     * 
     * @param string $project_id Proje ID'si
     * @return array Proje bilgileri
     */
    public function getProject($project_id) {
        return $this->request("/projects/{$project_id}");
    }
    
    /**
     * Tüm projeleri listele
     * 
     * @return array Projeler
     */
    public function getProjects() {
        return $this->request('/projects');
    }
    
    /**
     * Projeyi sil
     * 
     * @param string $project_id Proje ID'si
     * @return array Sonuç
     */
    public function deleteProject($project_id) {
        return $this->request("/projects/{$project_id}", [], 'DELETE');
    }
    
    /**
     * Kullanıcı bilgilerini getir
     * 
     * @return array Kullanıcı bilgileri
     */
    public function getUserInfo() {
        return $this->request('/user');
    }
    
    /**
     * Abonelik bilgilerini getir
     * 
     * @return array Abonelik bilgileri
     */
    public function getSubscriptionInfo() {
        return $this->request('/subscription');
    }
    
    /**
     * APIInterface: complete metodu
     */
    public function complete($prompt, $options = []) {
        return $this->textToSpeech($prompt, $options);
    }
    
    /**
     * APIInterface: stream metodu
     */
    public function stream($prompt, $callback, $options = []) {
        return $this->streamTextToSpeech($prompt, $callback, $options);
    }
    
    /**
     * APIInterface: getModels metodu
     */
    public function getModels() {
        return [
            'eleven_monolingual_v1' => 'Monolingual (English)',
            'eleven_multilingual_v1' => 'Multilingual',
            'eleven_turbo_v1' => 'Turbo (Fast)',
            'eleven_turbo_v2' => 'Turbo v2 (Fastest)'
        ];
    }
    
    /**
     * APIInterface: getCapabilities metodu
     */
    public function getCapabilities() {
        return [
            'text_to_speech',
            'voice_cloning',
            'voice_design',
            'streaming',
            'projects'
        ];
    }
    
    /**
     * APIInterface: checkHealth metodu
     */
    public function checkHealth() {
        $result = $this->getUserInfo();
        return $result['success'] ?? false;
    }
    
    /**
     * APIInterface: setApiKey metodu
     */
    public function setApiKey($api_key) {
        $this->api_key = $api_key;
        $this->config['api_key'] = $api_key;
    }
    
    /**
     * APIInterface: setModel metodu
     */
    public function setModel($model) {
        $this->config['default_model'] = $model;
        return true;
    }
    
    /**
     * APIInterface: getModel metodu
     */
    public function getModel() {
        return $this->config['default_model'];
    }
    
    /**
     * APIInterface: chat metodu
     */
    public function chat($messages, $options = []) {
        $last_message = end($messages);
        $text = $last_message['content'] ?? '';
        return $this->textToSpeech($text, $options);
    }
}