# API Master - API Dokümantasyonu

## 📌 Genel Bilgiler

API Master, 65+ farklı API provider'ını tek bir noktadan yönetmenizi sağlayan güçlü bir API entegrasyon modülüdür.

### Temel Özellikler
- ✅ 65+ API provider desteği
- ✅ HNSW tabanlı vektör indeksleme
- ✅ Yapay öğrenme ile akıllı routing
- ✅ Otomatik failover ve load balancing
- ✅ Gerçek zamanlı log ve metrikler
- ✅ Memory consolidation sistemi

### Sistem Gereksinimleri
- PHP 7.4+
- cURL extension
- JSON extension
- OpenSSL extension
- MBString extension

---

## 🔌 API Endpoint'leri

### 1. Provider Yönetimi

#### `GET /api/v1/providers`
Tüm provider'ları listeler

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": "openai",
            "name": "OpenAI",
            "status": "active",
            "version": "v1",
            "endpoints": ["/completions", "/embeddings"]
        }
    ]
}


GET /api/v1/providers/{id}
Belirli bir provider'ın detaylarını getirir

POST /api/v1/providers/{id}/test
Provider bağlantısını test eder

POST /api/v1/providers/{id}/enable
Provider'ı aktifleştirir

POST /api/v1/providers/{id}/disable
Provider'ı pasifleştirir

2. API İstekleri
POST /api/v1/request
Genel API isteği gönderir

Request Body:

json
{
    "provider": "openai",
    "endpoint": "/v1/chat/completions",
    "method": "POST",
    "headers": {},
    "body": {
        "model": "gpt-3.5-turbo",
        "messages": [{"role": "user", "content": "Hello"}]
    }
}
Response:

json
{
    "success": true,
    "data": {},
    "response_time_ms": 245,
    "provider_used": "openai"
}
3. Vektör İşlemleri
POST /api/v1/vector/embed
Metni vektöre dönüştürür

Request Body:

json
{
    "text": "API Master bir API yönetim modülüdür",
    "provider": "openai"
}
Response:

json
{
    "success": true,
    "embedding": [0.123, -0.456, 0.789, ...],
    "dimensions": 1536
}
POST /api/v1/vector/search
Vektör benzerlik araması yapar

Request Body:

json
{
    "vector": [0.123, -0.456, 0.789, ...],
    "limit": 10,
    "threshold": 0.7
}
4. Learning & Memory
GET /api/v1/learning/stats
Öğrenme istatistiklerini getirir

Response:

json
{
    "memory_consolidation": "active",
    "patterns_learned": 15420,
    "prediction_accuracy": 94.5,
    "samples_per_day": 1250
}
POST /api/v1/learning/train
Model eğitimi başlatır

Request Body:

json
{
    "samples": [
        {"input": "...", "output": "..."}
    ],
    "epochs": 10
}
5. Sistem Durumu
GET /api/v1/health
Sistem sağlık durumunu kontrol eder

Response:

json
{
    "status": "healthy",
    "timestamp": "2024-01-15T10:30:00Z",
    "components": {
        "api": "up",
        "vector_index": "up",
        "learning": "up",
        "cache": "up"
    }
}
GET /api/v1/stats
Sistem istatistiklerini getirir

6. Loglar
GET /api/v1/logs
Sistem loglarını getirir

Query Parameters:

level - Log seviyesi (info, warning, error)

limit - Maksimum kayıt sayısı

offset - Sayfalama için başlangıç

from - Başlangıç tarihi

to - Bitiş tarihi

📊 Hata Kodları
Kod	Açıklama
200	Başarılı
400	Geçersiz istek
401	Yetkilendirme hatası
403	Erişim engellendi
404	Endpoint bulunamadı
429	Rate limit aşıldı
500	Sunucu hatası
🔐 Kimlik Doğrulama
API Master, aşağıdaki kimlik doğrulama yöntemlerini destekler:

API Key (Önerilen)
http
Authorization: Bearer YOUR_API_KEY
X-API-Key: YOUR_API_KEY
JWT Token
http
Authorization: Bearer JWT_TOKEN
🚦 Rate Limiting
Varsayılan: 1000 istek/dakika

Enterprise: 10000 istek/dakika

Rate limit aşıldığında 429 hatası döner

📝 Örnek Kullanımlar
cURL ile OpenAI API çağrısı
bash
curl -X POST http://localhost/api-master/api/v1/request \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "provider": "openai",
    "endpoint": "/v1/chat/completions",
    "body": {
      "model": "gpt-3.5-turbo",
      "messages": [{"role": "user", "content": "Merhaba"}]
    }
  }'
PHP ile vektör araması
php
<?php
$response = $apiMaster->vectorSearch([
    'vector' => $embedding,
    'limit' => 10,
    'threshold' => 0.75
]);

if ($response['success']) {
    foreach ($response['results'] as $result) {
        echo "Score: " . $result['similarity'] . "\n";
        echo "Data: " . $result['data'] . "\n";
    }
}
📞 Destek
Dokümantasyon: /docs/

GitHub: github.com/api-master

Email: support@apimaster.com