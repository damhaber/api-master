<?php
/**
 * OpenWeatherMap API Sınıfı
 * 
 * OpenWeatherMap API
 * - Güncel hava durumu
 * - 5 günlük tahmin
 * - Hava durumu haritası
 * - Hava kirliliği
 * - UV indeksi
 * 
 * @package APIMaster
 * @subpackage API
 * @since 1.0.0
 */

namespace APIMaster\API;

use APIMaster\Core\Logger;
use APIMaster\Core\Cache;
use APIMaster\Core\Validator;

class Weather implements APIInterface {
    
    /**
     * API endpoint'leri
     * @var array
     */
    private $endpoints = [
        'weather' => 'https://api.openweathermap.org/data/2.5/weather',
        'forecast' => 'https://api.openweathermap.org/data/2.5/forecast',
        'air_pollution' => 'https://api.openweathermap.org/data/2.5/air_pollution',
        'uv_index' => 'https://api.openweathermap.org/data/3.0/uv',
        'onecall' => 'https://api.openweathermap.org/data/3.0/onecall',
        'geocoding' => 'https://api.openweathermap.org/geo/1.0/direct',
        'reverse_geocoding' => 'https://api.openweathermap.org/geo/1.0/reverse'
    ];
    
    /**
     * API anahtarı
     * @var string
     */
    private $api_key;
    
    /**
     * Logger instance
     * @var Logger
     */
    private $logger;
    
    /**
     * Cache instance
     * @var Cache
     */
    private $cache;
    
    /**
     * Validator instance
     * @var Validator
     */
    private $validator;
    
    /**
     * Yapılandırma
     * @var array
     */
    private $config = [];
    
    /**
     * Constructor
     * 
     * @param array $config Yapılandırma ayarları
     */
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'api_key' => '',
            'units' => 'metric', // metric, imperial, standard
            'language' => 'tr',
            'cache_ttl' => 1800, // 30 dakika
            'timeout' => 30,
            'max_retries' => 3,
            'enable_cache' => true,
            'enable_logging' => true
        ], $config);
        
        $this->api_key = $this->config['api_key'];
        $this->logger = new Logger('weather');
        $this->cache = new Cache('weather');
        $this->validator = new Validator();
    }
    
    /**
     * API isteği gönder
     * 
     * @param string $endpoint Endpoint tipi
     * @param array $params Parametreler
     * @return array API yanıtı
     */
    public function request($endpoint, $params = []) {
        $cache_key = md5($endpoint . json_encode($params));
        
        if ($this->config['enable_cache']) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== false) {
                $this->logger->info('Weather cache hit', ['endpoint' => $endpoint]);
                return $cached;
            }
        }
        
        $params = array_merge([
            'appid' => $this->api_key,
            'units' => $this->config['units'],
            'lang' => $this->config['language']
        ], $params);
        
        $url = $this->endpoints[$endpoint] . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->logger->error('Weather request failed', ['error' => $error]);
            return [
                'success' => false,
                'error' => $error,
                'code' => $httpCode
            ];
        }
        
        $result = $this->parseResponse($response, $httpCode);
        
        if ($this->config['enable_cache'] && $result['success']) {
            $this->cache->set($cache_key, $result, $this->config['cache_ttl']);
        }
        
        return $result;
    }
    
    /**
     * Yanıtı parse et
     * 
     * @param string $response Ham yanıt
     * @param int $httpCode HTTP kodu
     * @return array Parse edilmiş yanıt
     */
    private function parseResponse($response, $httpCode) {
        $data = json_decode($response, true);
        
        if ($httpCode === 200 && $data !== null) {
            return [
                'success' => true,
                'data' => $data
            ];
        }
        
        $this->logger->error('Weather API error', [
            'http_code' => $httpCode,
            'response' => $response
        ]);
        
        return [
            'success' => false,
            'error' => $data['message'] ?? 'Unknown error',
            'code' => $httpCode,
            'cod' => $data['cod'] ?? null
        ];
    }
    
    /**
     * Şehir adına göre güncel hava durumu getir
     * 
     * @param string $city Şehir adı
     * @param array $options Seçenekler
     * @return array Hava durumu
     */
    public function getCurrentWeather($city, $options = []) {
        $params = ['q' => $city];
        
        if (isset($options['country_code'])) {
            $params['q'] = $city . ',' . $options['country_code'];
        }
        
        $result = $this->request('weather', $params);
        
        if ($result['success']) {
            $result['weather'] = $this->parseWeather($result['data']);
        }
        
        return $result;
    }
    
    /**
     * Koordinatlara göre güncel hava durumu getir
     * 
     * @param float $lat Enlem
     * @param float $lon Boylam
     * @return array Hava durumu
     */
    public function getCurrentWeatherByCoords($lat, $lon) {
        $params = [
            'lat' => $lat,
            'lon' => $lon
        ];
        
        $result = $this->request('weather', $params);
        
        if ($result['success']) {
            $result['weather'] = $this->parseWeather($result['data']);
        }
        
        return $result;
    }
    
    /**
     * Hava durumu verisini parse et
     * 
     * @param array $data Ham veri
     * @return array Parse edilmiş hava durumu
     */
    private function parseWeather($data) {
        $unit_symbols = [
            'metric' => ['temp' => '°C', 'speed' => 'm/s'],
            'imperial' => ['temp' => '°F', 'speed' => 'mph'],
            'standard' => ['temp' => 'K', 'speed' => 'm/s']
        ];
        
        $units = $unit_symbols[$this->config['units']];
        
        return [
            'location' => [
                'name' => $data['name'],
                'country' => $data['sys']['country'],
                'lat' => $data['coord']['lat'],
                'lon' => $data['coord']['lon']
            ],
            'weather' => [
                'main' => $data['weather'][0]['main'],
                'description' => $data['weather'][0]['description'],
                'icon' => $data['weather'][0]['icon'],
                'icon_url' => "https://openweathermap.org/img/wn/{$data['weather'][0]['icon']}@2x.png"
            ],
            'temperature' => [
                'current' => $data['main']['temp'],
                'feels_like' => $data['main']['feels_like'],
                'min' => $data['main']['temp_min'],
                'max' => $data['main']['temp_max'],
                'unit' => $units['temp']
            ],
            'pressure' => [
                'value' => $data['main']['pressure'],
                'unit' => 'hPa'
            ],
            'humidity' => [
                'value' => $data['main']['humidity'],
                'unit' => '%'
            ],
            'wind' => [
                'speed' => $data['wind']['speed'],
                'unit' => $units['speed'],
                'direction' => $data['wind']['deg'] ?? null,
                'gust' => $data['wind']['gust'] ?? null
            ],
            'clouds' => $data['clouds']['all'] ?? null,
            'visibility' => $data['visibility'] ?? null,
            'sunrise' => date('H:i:s', $data['sys']['sunrise']),
            'sunset' => date('H:i:s', $data['sys']['sunset']),
            'timezone' => $data['timezone'] ?? null,
            'datetime' => date('Y-m-d H:i:s', $data['dt'])
        ];
    }
    
    /**
     * 5 günlük hava tahmini getir
     * 
     * @param string $city Şehir adı
     * @param array $options Seçenekler
     * @return array Hava tahmini
     */
    public function getForecast($city, $options = []) {
        $params = ['q' => $city];
        
        if (isset($options['country_code'])) {
            $params['q'] = $city . ',' . $options['country_code'];
        }
        
        $result = $this->request('forecast', $params);
        
        if ($result['success'] && isset($result['data']['list'])) {
            $forecast = [];
            $current_date = '';
            $daily_forecast = [];
            
            foreach ($result['data']['list'] as $item) {
                $date = date('Y-m-d', $item['dt']);
                $time = date('H:i', $item['dt']);
                
                $forecast_item = [
                    'datetime' => $date . ' ' . $time,
                    'timestamp' => $item['dt'],
                    'date' => $date,
                    'time' => $time,
                    'temperature' => $item['main']['temp'],
                    'feels_like' => $item['main']['feels_like'],
                    'temp_min' => $item['main']['temp_min'],
                    'temp_max' => $item['main']['temp_max'],
                    'pressure' => $item['main']['pressure'],
                    'humidity' => $item['main']['humidity'],
                    'weather' => [
                        'main' => $item['weather'][0]['main'],
                        'description' => $item['weather'][0]['description'],
                        'icon' => $item['weather'][0]['icon']
                    ],
                    'wind_speed' => $item['wind']['speed'],
                    'wind_direction' => $item['wind']['deg'] ?? null,
                    'clouds' => $item['clouds']['all'] ?? null,
                    'rain' => $item['rain']['3h'] ?? null,
                    'snow' => $item['snow']['3h'] ?? null,
                    'pop' => ($item['pop'] ?? 0) * 100 // Yağış olasılığı (%)
                ];
                
                $forecast[] = $forecast_item;
                
                // Günlük özet oluştur
                if ($current_date !== $date) {
                    if ($current_date !== '') {
                        $daily_forecast[] = $this->calculateDailySummary($daily_items);
                    }
                    $current_date = $date;
                    $daily_items = [];
                }
                $daily_items[] = $forecast_item;
            }
            
            // Son günü ekle
            if (!empty($daily_items)) {
                $daily_forecast[] = $this->calculateDailySummary($daily_items);
            }
            
            $result['city'] = [
                'name' => $result['data']['city']['name'],
                'country' => $result['data']['city']['country'],
                'population' => $result['data']['city']['population'] ?? null,
                'timezone' => $result['data']['city']['timezone'] ?? null
            ];
            
            $result['forecast'] = $forecast;
            $result['daily_forecast'] = $daily_forecast;
        }
        
        return $result;
    }
    
    /**
     * Günlük hava durumu özeti hesapla
     * 
     * @param array $items Gün içindeki tahminler
     * @return array Günlük özet
     */
    private function calculateDailySummary($items) {
        $temps = array_column($items, 'temperature');
        $temps_min = array_column($items, 'temp_min');
        $temps_max = array_column($items, 'temp_max');
        
        // En sık görülen hava durumunu bul
        $weather_counts = [];
        foreach ($items as $item) {
            $weather = $item['weather']['main'];
            $weather_counts[$weather] = ($weather_counts[$weather] ?? 0) + 1;
        }
        $dominant_weather = array_keys($weather_counts, max($weather_counts))[0];
        
        // İlk item'ın tarihini kullan
        $first_item = $items[0];
        
        return [
            'date' => $first_item['date'],
            'temperature' => [
                'avg' => array_sum($temps) / count($temps),
                'min' => min($temps_min),
                'max' => max($temps_max)
            ],
            'weather' => [
                'main' => $dominant_weather,
                'description' => $this->getWeatherDescription($dominant_weather),
                'icon' => $this->getWeatherIcon($dominant_weather)
            ],
            'wind_speed_avg' => array_sum(array_column($items, 'wind_speed')) / count($items),
            'humidity_avg' => array_sum(array_column($items, 'humidity')) / count($items),
            'pop_avg' => array_sum(array_column($items, 'pop')) / count($items),
            'rain_total' => array_sum(array_column($items, 'rain')),
            'snow_total' => array_sum(array_column($items, 'snow'))
        ];
    }
    
    /**
     * Hava durumu açıklamasını getir
     * 
     * @param string $weather_main Ana hava durumu
     * @return string Açıklama
     */
    private function getWeatherDescription($weather_main) {
        $descriptions = [
            'Clear' => 'Açık',
            'Clouds' => 'Bulutlu',
            'Rain' => 'Yağmurlu',
            'Drizzle' => 'Çisenti',
            'Thunderstorm' => 'Fırtınalı',
            'Snow' => 'Karlı',
            'Mist' => 'Sisli',
            'Smoke' => 'Dumanlı',
            'Haze' => 'Puslu',
            'Fog' => 'Sis',
            'Sand' => 'Kum Fırtınası',
            'Dust' => 'Tozlu',
            'Ash' => 'Kül',
            'Squall' => 'Bora',
            'Tornado' => 'Kasırga'
        ];
        
        return $descriptions[$weather_main] ?? $weather_main;
    }
    
    /**
     * Hava durumu ikonunu getir
     * 
     * @param string $weather_main Ana hava durumu
     * @return string İkon kodu
     */
    private function getWeatherIcon($weather_main) {
        $icons = [
            'Clear' => '01d',
            'Clouds' => '03d',
            'Rain' => '10d',
            'Drizzle' => '09d',
            'Thunderstorm' => '11d',
            'Snow' => '13d',
            'Mist' => '50d',
            'Smoke' => '50d',
            'Haze' => '50d',
            'Fog' => '50d'
        ];
        
        return $icons[$weather_main] ?? '03d';
    }
    
    /**
     * Hava kirliliği verilerini getir
     * 
     * @param float $lat Enlem
     * @param float $lon Boylam
     * @return array Hava kirliliği
     */
    public function getAirPollution($lat, $lon) {
        $params = [
            'lat' => $lat,
            'lon' => $lon
        ];
        
        $result = $this->request('air_pollution', $params);
        
        if ($result['success'] && isset($result['data']['list'][0])) {
            $data = $result['data']['list'][0];
            $components = $data['components'];
            
            $result['air_quality'] = [
                'aqi' => $data['main']['aqi'],
                'aqi_description' => $this->getAQIDescription($data['main']['aqi']),
                'components' => [
                    'co' => ['value' => $components['co'], 'unit' => 'μg/m³', 'name' => 'Karbon Monoksit'],
                    'no' => ['value' => $components['no'], 'unit' => 'μg/m³', 'name' => 'Nitrik Oksit'],
                    'no2' => ['value' => $components['no2'], 'unit' => 'μg/m³', 'name' => 'Nitrogen Dioksit'],
                    'o3' => ['value' => $components['o3'], 'unit' => 'μg/m³', 'name' => 'Ozon'],
                    'so2' => ['value' => $components['so2'], 'unit' => 'μg/m³', 'name' => 'Sülfür Dioksit'],
                    'pm2_5' => ['value' => $components['pm2_5'], 'unit' => 'μg/m³', 'name' => 'PM2.5'],
                    'pm10' => ['value' => $components['pm10'], 'unit' => 'μg/m³', 'name' => 'PM10'],
                    'nh3' => ['value' => $components['nh3'], 'unit' => 'μg/m³', 'name' => 'Amonyak']
                ]
            ];
        }
        
        return $result;
    }
    
    /**
     * AQI açıklamasını getir
     * 
     * @param int $aqi AQI değeri (1-5)
     * @return string Açıklama
     */
    private function getAQIDescription($aqi) {
        $descriptions = [
            1 => 'İyi',
            2 => 'Orta',
            3 => 'Hassas Gruplar için Kötü',
            4 => 'Kötü',
            5 => 'Çok Kötü'
        ];
        
        return $descriptions[$aqi] ?? 'Bilinmiyor';
    }
    
    /**
     * UV indeksi getir
     * 
     * @param float $lat Enlem
     * @param float $lon Boylam
     * @return array UV indeksi
     */
    public function getUVIndex($lat, $lon) {
        $params = [
            'lat' => $lat,
            'lon' => $lon
        ];
        
        $result = $this->request('uv_index', $params);
        
        if ($result['success']) {
            $uv = $result['data']['value'];
            $result['uv'] = [
                'value' => $uv,
                'risk_level' => $this->getUVRiskLevel($uv),
                'recommendation' => $this->getUVRecommendation($uv)
            ];
        }
        
        return $result;
    }
    
    /**
     * UV risk seviyesini getir
     * 
     * @param float $uv UV değeri
     * @return string Risk seviyesi
     */
    private function getUVRiskLevel($uv) {
        if ($uv < 3) return 'Düşük';
        if ($uv < 6) return 'Orta';
        if ($uv < 8) return 'Yüksek';
        if ($uv < 11) return 'Çok Yüksek';
        return 'Tehlikeli';
    }
    
    /**
     * UV önerisini getir
     * 
     * @param float $uv UV değeri
     * @return string Öneri
     */
    private function getUVRecommendation($uv) {
        if ($uv < 3) {
            return 'Korunmaya gerek yok';
        } elseif ($uv < 6) {
            return 'Öğle saatlerinde gölgede kalın';
        } elseif ($uv < 8) {
            return 'Güneş kremi ve şapka kullanın';
        } elseif ($uv < 11) {
            return 'Kesinlikle korunun, dışarı çıkmayın';
        } else {
            return 'Tehlikeli, dışarı çıkmayın';
        }
    }
    
    /**
     * OneCall API (kapsamlı hava durumu)
     * 
     * @param float $lat Enlem
     * @param float $lon Boylam
     * @param array $options Seçenekler
     * @return array Kapsamlı hava durumu
     */
    public function getOneCall($lat, $lon, $options = []) {
        $options = array_merge([
            'exclude' => null, // current,minutely,hourly,daily,alerts
            'units' => $this->config['units']
        ], $options);
        
        $params = [
            'lat' => $lat,
            'lon' => $lon
        ];
        
        if ($options['exclude']) {
            $params['exclude'] = $options['exclude'];
        }
        
        $result = $this->request('onecall', $params);
        
        if ($result['success']) {
            $data = $result['data'];
            
            $parsed = [];
            
            // Güncel hava durumu
            if (isset($data['current'])) {
                $parsed['current'] = [
                    'dt' => date('Y-m-d H:i:s', $data['current']['dt']),
                    'temperature' => $data['current']['temp'],
                    'feels_like' => $data['current']['feels_like'],
                    'pressure' => $data['current']['pressure'],
                    'humidity' => $data['current']['humidity'],
                    'dew_point' => $data['current']['dew_point'],
                    'uvi' => $data['current']['uvi'],
                    'clouds' => $data['current']['clouds'],
                    'visibility' => $data['current']['visibility'] ?? null,
                    'wind_speed' => $data['current']['wind_speed'],
                    'wind_deg' => $data['current']['wind_deg'],
                    'weather' => $data['current']['weather'][0]
                ];
            }
            
            // Günlük tahmin
            if (isset($data['daily'])) {
                $parsed['daily'] = [];
                foreach ($data['daily'] as $day) {
                    $parsed['daily'][] = [
                        'dt' => date('Y-m-d', $day['dt']),
                        'sunrise' => date('H:i:s', $day['sunrise']),
                        'sunset' => date('H:i:s', $day['sunset']),
                        'moonrise' => date('H:i:s', $day['moonrise']),
                        'moonset' => date('H:i:s', $day['moonset']),
                        'moon_phase' => $day['moon_phase'],
                        'temp' => [
                            'day' => $day['temp']['day'],
                            'min' => $day['temp']['min'],
                            'max' => $day['temp']['max'],
                            'night' => $day['temp']['night'],
                            'eve' => $day['temp']['eve'],
                            'morn' => $day['temp']['morn']
                        ],
                        'feels_like' => $day['feels_like'],
                        'pressure' => $day['pressure'],
                        'humidity' => $day['humidity'],
                        'dew_point' => $day['dew_point'],
                        'wind_speed' => $day['wind_speed'],
                        'wind_deg' => $day['wind_deg'],
                        'weather' => $day['weather'][0],
                        'clouds' => $day['clouds'],
                        'pop' => ($day['pop'] ?? 0) * 100,
                        'rain' => $day['rain'] ?? null,
                        'uvi' => $day['uvi']
                    ];
                }
            }
            
            // Uyarılar
            if (isset($data['alerts'])) {
                $parsed['alerts'] = [];
                foreach ($data['alerts'] as $alert) {
                    $parsed['alerts'][] = [
                        'sender_name' => $alert['sender_name'],
                        'event' => $alert['event'],
                        'start' => date('Y-m-d H:i:s', $alert['start']),
                        'end' => date('Y-m-d H:i:s', $alert['end']),
                        'description' => $alert['description'],
                        'tags' => $alert['tags']
                    ];
                }
            }
            
            $result['onecall'] = $parsed;
        }
        
        return $result;
    }
    
    /**
     * Şehir ara (geocoding)
     * 
     * @param string $city_name Şehir adı
     * @param int $limit Limit
     * @return array Şehir listesi
     */
    public function searchCity($city_name, $limit = 5) {
        $params = [
            'q' => $city_name,
            'limit' => $limit
        ];
        
        $result = $this->request('geocoding', $params);
        
        if ($result['success'] && is_array($result['data'])) {
            $cities = [];
            foreach ($result['data'] as $city) {
                $cities[] = [
                    'name' => $city['name'],
                    'country' => $city['country'],
                    'state' => $city['state'] ?? null,
                    'lat' => $city['lat'],
                    'lon' => $city['lon']
                ];
            }
            $result['cities'] = $cities;
        }
        
        return $result;
    }
    
    /**
     * Koordinatlardan şehir bul (reverse geocoding)
     * 
     * @param float $lat Enlem
     * @param float $lon Boylam
     * @return array Şehir bilgisi
     */
    public function reverseGeocode($lat, $lon) {
        $params = [
            'lat' => $lat,
            'lon' => $lon,
            'limit' => 1
        ];
        
        $result = $this->request('reverse_geocoding', $params);
        
        if ($result['success'] && isset($result['data'][0])) {
            $city = $result['data'][0];
            $result['city'] = [
                'name' => $city['name'],
                'country' => $city['country'],
                'state' => $city['state'] ?? null,
                'lat' => $city['lat'],
                'lon' => $city['lon']
            ];
        }
        
        return $result;
    }
    
    /**
     * APIInterface: complete metodu
     */
    public function complete($prompt, $options = []) {
        return $this->getCurrentWeather($prompt, $options);
    }
    
    /**
     * APIInterface: stream metodu
     */
    public function stream($prompt, $callback, $options = []) {
        $result = $this->getCurrentWeather($prompt, $options);
        $callback($result);
        return $result;
    }
    
    /**
     * APIInterface: getModels metodu
     */
    public function getAvailableModels() {
        return [
            'current_weather' => 'Current Weather',
            'forecast' => '5 Day Forecast',
            'air_pollution' => 'Air Pollution',
            'uv_index' => 'UV Index',
            'onecall' => 'OneCall API',
            'geocoding' => 'City Search'
        ];
    }
}