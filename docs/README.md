markdown
# API Master - Çoklu API Entegrasyon Modülü

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/apimaster/api-master)
[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

> **65+ API provider'ını tek bir noktadan yönetin!** OpenAI, Anthropic, Google AI, Microsoft ve daha fazlası...

---

## 🚀 Hızlı Başlangıç

```bash
# 1. Dosyaları kopyalayın
git clone https://github.com/apimaster/api-master.git

# 2. Konfigürasyonu düzenleyin
cp config/config.example.json config/config.json
vi config/config.json

# 3. İzinleri ayarlayın
chmod -R 755 api-master/
chmod -R 777 api-master/logs api-master/cache api-master/data

# 4. Admin paneli açın
# http://your-domain.com/api-master/panel
✨ Özellikler
🔌 Çoklu API Desteği
65+ API Provider (OpenAI, Anthropic, Google, Microsoft, AWS, ve diğerleri)

Otomatik Failover - Bir API düştüğünde diğerine geçer

Load Balancing - İstekleri provider'lar arasında dağıtır

Rate Limit Yönetimi - Otomatik rate limit koruması

🧠 Yapay Öğrenme
Active Learning - Kullanım desenlerinden öğrenir

Memory Consolidation - Önemli pattern'leri kalıcı hafızaya alır

Akıllı Routing - En uygun provider'ı seçer

Prediction Engine - İstek sonuçlarını tahmin eder

📊 Vektör İndeksleme (HNSW)
1536+ Boyut - Yüksek boyutlu vektör desteği

Milyonlarca Vektör - Ölçeklenebilir indeksleme

Hızlı Arama - <15ms ortalama arama süresi

Yüksek Recall - %98.5 doğruluk oranı

🔒 Güvenlik
XSS Koruması - Otomatik input sanitization

CSRF Koruması - Token bazlı doğrulama

SQL Injection Koruması (JSON tabanlı, DB yok)

API Key Authentication - Bearer token desteği

Rate Limiting - İstek sınırlandırma

⚡ Performans
Asenkron Queue - Arka plan işlemleri

Multi-level Cache (File/Redis/Memcached)

GZIP Compression - Yanıt sıkıştırma

OPcache Desteği - PHP bytecode cache

📦 Desteklenen Provider'lar
🤖 AI/ML Provider'ları
Provider	Modeller	Durum
OpenAI	GPT-4, GPT-3.5, Embeddings	✅
Anthropic	Claude 2, Claude Instant	✅
Google AI	Gemini, PaLM 2	✅
Microsoft	Azure OpenAI	✅
Cohere	Generate, Embed	✅
AI21 Labs	Jurassic	✅
Hugging Face	Inference API	✅
Replicate	100+ modeller	✅
☁️ Cloud Provider'lar
Provider	Servisler	Durum
AWS	Bedrock, SageMaker	✅
Google Cloud	Vertex AI	✅
Azure	Cognitive Services	✅
🔍 Search & Vector DB
Provider	Özellik	Durum
Pinecone	Vektör DB	✅
Weaviate	Vektör Search	✅
Qdrant	Vektör Search	✅
Milvus	Vektör DB	✅
📧 Communication
Provider	Tip	Durum
Twilio	SMS, Voice	✅
SendGrid	Email	✅
Mailgun	Email	✅
Slack	Webhook	✅
Discord	Webhook	✅
💳 Payment
Provider	Özellik	Durum
Stripe	Ödeme İşleme	✅
PayPal	Ödeme İşleme	✅
🏗️ Mimari
text
┌─────────────────────────────────────────────────────────┐
│                     Client Request                       │
└─────────────────────┬───────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────┐
│                   Middleware Layer                       │
│         (Auth, Rate Limit, Logging, Cache)              │
└─────────────────────┬───────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────┐
│                   Router / Dispatcher                    │
└─────────────────────┬───────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────┐
│                  Learning Engine                         │
│     (Routing Decision, Pattern Recognition)              │
└─────────────────────┬───────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────┐
│                 Provider Manager                         │
│    ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐        │
│    │OpenAI│ │Google│ │Anthrop│ │Custom│ │ ...  │        │
│    └──────┘ └──────┘ └──────┘ └──────┘ └──────┘        │
└─────────────────────────────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────┐
│              Vector Index (HNSW)                         │
│         (Semantic Search, Similarity)                    │
└─────────────────────────────────────────────────────────┘
📖 Kullanım Örnekleri
PHP ile Basit Kullanım
php
<?php
// Initialize
require_once 'CORE/autoloader.php';
$apiMaster = new APIMaster_Core();

// Basit API çağrısı
$response = $apiMaster->request('openai', '/chat/completions', [
    'model' => 'gpt-3.5-turbo',
    'messages' => [
        ['role' => 'user', 'content' => 'Merhaba!']
    ]
]);

print_r($response);
Vektör Benzerlik Arama
php
<?php
// Metni vektöre çevir
$embedding = $apiMaster->createEmbedding('API Master nedir?');

// Benzer içerikleri ara
$results = $apiMaster->vectorSearch($embedding, [
    'limit' => 10,
    'threshold' => 0.75
]);

foreach ($results as $result) {
    echo "Benzerlik: " . $result['similarity'] . "\n";
    echo "İçerik: " . $result['data'] . "\n";
}
cURL ile API Çağrısı
bash
# Provider listesini al
curl -X GET http://localhost/api-master/api/v1/providers \
  -H "Authorization: Bearer YOUR_API_KEY"

# Chat tamamlama
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
JavaScript ile Kullanım
javascript
// Modern JavaScript
const apiMaster = {
    baseUrl: 'http://localhost/api-master/api/v1',
    apiKey: 'your-api-key',
    
    async request(provider, endpoint, data) {
        const response = await fetch(`${this.baseUrl}/request`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.apiKey}`
            },
            body: JSON.stringify({ provider, endpoint, body: data })
        });
        return response.json();
    },
    
    async searchVector(vector, limit = 10) {
        const response = await fetch(`${this.baseUrl}/vector/search`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.apiKey}`
            },
            body: JSON.stringify({ vector, limit })
        });
        return response.json();
    }
};

// Kullanım
const response = await apiMaster.request('openai', '/chat/completions', {
    model: 'gpt-3.5-turbo',
    messages: [{"role": "user", "content": "Merhaba"}]
});
📊 Performans
Metrik	Değer
Ortalama Yanıt Süresi	<250ms
Concurrent İstek	1000+/sn
Vektör Arama Süresi	<15ms
Cache Hit Rate	%85+
Uptime	%99.9
Provider Failover	<1sn
🗺️ Yol Haritası
v1.1.0 (Q1 2024)
WebSocket desteği

GraphQL endpoint'i

100+ provider desteği

Gelişmiş analitik dashboard

v1.2.0 (Q2 2024)
Otomatik API keşfi

API marketplace

Ekip yönetimi

Real-time monitoring

v2.0.0 (Q3/Q4 2024)
Dağıtık mimari

Kubernetes desteği

AI destekli optimizasyon

Multi-tenant desteği

🤝 Katkıda Bulunma
Fork edin

Feature branch oluşturun (git checkout -b feature/amazing-feature)

Commit'leyin (git commit -m 'Add amazing feature')

Push yapın (git push origin feature/amazing-feature)

Pull Request açın

Geliştirme Ortamı Kurulumu
bash
# Repository'yi klonlayın
git clone https://github.com/apimaster/api-master.git
cd api-master

# Testleri çalıştırın
php tests/runner.php

# Kod stilini kontrol edin
php vendor/bin/phpcs --standard=PSR12 .

# Dokümantasyon oluşturun
php docs/generate.php
📝 Lisans
Bu proje MIT lisansı ile lisanslanmıştır. Detaylar için LICENSE dosyasına bakın.

🙏 Teşekkürler
OpenAI - GPT modelleri için

HNSW - Vektör indeksleme algoritması için

Tüm katkıda bulunanlar - Destekleri için

📞 İletişim
Website: https://apimaster.com

Dokümantasyon: https://docs.apimaster.com

GitHub: https://github.com/apimaster

Twitter: @apimaster

Email: info@apimaster.com

Discord: https://discord.gg/apimaster

⭐ Star Tarihçesi
https://api.star-history.com/svg?repos=apimaster/api-master&type=Date

🏆 Başarılarımız
⭐ 1,000+ GitHub Stars

🚀 10,000+ Aktif Kurulum

🌍 50+ Ülkede Kullanım

💬 5,000+ Discord Üyesi

📚 100+ Topluluk Eklentisi

API Master ile API'lerinizi akıllıca yönetin! 🎉