markdown
# API Master - Kurulum Dokümantasyonu

## 📋 Sistem Gereksinimleri

### Minimum Gereksinimler
- **PHP**: 7.4 veya üzeri
- **Web Sunucusu**: Apache 2.4+ / Nginx 1.18+
- **cURL**: PHP cURL extension
- **JSON**: PHP JSON extension
- **OpenSSL**: PHP OpenSSL extension
- **MBString**: PHP MBString extension
- **Disk Alanı**: En az 100MB
- **RAM**: En az 256MB (önerilen 512MB)

### Önerilen Gereksinimler
- **PHP**: 8.0 veya üzeri
- **RAM**: 1GB+
- **Redis/Memcached**: Cache için (opsiyonel)
- **OPcache**: PHP OPcache extension

---

## 🔧 Kurulum Öncesi Kontroller

### 1. PHP Extension Kontrolü
```bash
# PHP extension'larını kontrol et
php -m | grep -E "curl|json|openssl|mbstring"

# Çıktı şöyle olmalı:
curl
json
openssl
mbstring
2. cURL Kontrolü
bash
# cURL kurulu mu kontrol et
curl --version

# PHP cURL aktif mi kontrol et
php -r "echo extension_loaded('curl') ? 'cURL aktif' : 'cURL aktif değil';"
3. Yazma İzinleri Kontrolü
bash
# Mevcut kullanıcıyı kontrol et
whoami

# Web sunucusu kullanıcısını öğren (genelde www-data veya nobody)
ps aux | grep -E "apache|nginx|httpd"
📦 Kurulum Adımları
Adım 1: Dosyaları İndirin
Yöntem A: Git ile
bash
cd /var/www/html/
git clone https://github.com/apimaster/api-master.git
cd api-master
Yöntem B: ZIP olarak
bash
cd /var/www/html/
wget https://github.com/apimaster/api-master/archive/main.zip
unzip main.zip
mv api-master-main api-master
cd api-master
Yöntem C: Manuel
bash
# Dosyaları FTP veya SSH ile /var/www/html/api-master/ dizinine kopyalayın
Adım 2: Dizin Yapısını Oluşturun
bash
# Gerekli dizinleri oluştur
mkdir -p logs cache data temp
mkdir -p config/backups
mkdir -p learning/models
mkdir -p vector/indexes

# İzinleri ayarla
chmod 755 logs cache data temp
chmod 755 config/backups
chmod 755 learning/models
chmod 755 vector/indexes
Adım 3: Konfigürasyon Dosyasını Oluşturun
bash
# Config dosyasını kopyala
cp config/config.example.json config/config.json

# Config dosyasını düzenle
vi config/config.json
config.json Örneği:
json
{
    "system": {
        "name": "API Master",
        "version": "1.0.0",
        "debug": false,
        "timezone": "Europe/Istanbul",
        "log_level": "info"
    },
    "security": {
        "api_key": "your-super-secret-api-key-here",
        "jwt_secret": "your-jwt-secret-key-here",
        "rate_limit": 1000,
        "rate_limit_window": 60
    },
    "cache": {
        "driver": "file",
        "ttl": 3600,
        "redis": {
            "host": "127.0.0.1",
            "port": 6379,
            "password": null,
            "database": 0
        },
        "memcached": {
            "host": "127.0.0.1",
            "port": 11211
        }
    },
    "providers": {
        "openai": {
            "enabled": true,
            "api_key": "sk-...",
            "timeout": 30,
            "retries": 3
        },
        "anthropic": {
            "enabled": false,
            "api_key": "sk-ant-...",
            "timeout": 30,
            "retries": 3
        }
    },
    "vector": {
        "dimensions": 1536,
        "max_elements": 1000000,
        "m": 16,
        "ef_construction": 200,
        "ef_search": 50
    },
    "learning": {
        "enabled": true,
        "auto_train": true,
        "consolidation_interval": 3600,
        "model_path": "learning/models/"
    }
}
Adım 4: Web Sunucusu Yapılandırması
Apache (.htaccess)
apache
# .htaccess dosyasını api-master dizinine koyun

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /api-master/
    
    # API routes
    RewriteRule ^api/v1/(.*)$ API/router.php?endpoint=$1 [QSA,L]
    
    # Admin panel
    RewriteRule ^panel$ ui/panel.php [L]
    RewriteRule ^panel/(.*)$ ui/panel.php?page=$1 [QSA,L]
    
    # Assets
    RewriteRule ^assets/(.*)$ assets/$1 [L]
    
    # Security: Hide sensitive files
    <FilesMatch "\.(json|log|ini|config)$">
        Require all denied
    </FilesMatch>
    
    # Block direct access to PHP files
    <FilesMatch "^(?!panel\.php|router\.php).*\.php$">
        Require all denied
    </FilesMatch>
</IfModule>

# PHP ayarları
<IfModule mod_php7.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 10M
    php_value max_execution_time 300
    php_value memory_limit 256M
</IfModule>
Nginx
nginx
# /etc/nginx/sites-available/api-master

server {
    listen 80;
    server_name api-master.local;
    root /var/www/html/api-master;
    index index.php;

    # API routes
    location /api/ {
        try_files $uri $uri/ /API/router.php?$query_string;
    }

    # Admin panel
    location /panel {
        try_files $uri $uri/ /ui/panel.php?$query_string;
    }

    # Assets
    location /assets/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Protect config files
    location ~ \.(json|log|ini|config)$ {
        deny all;
    }

    # PHP processing
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
}
Adım 5: Cron Job'ları Ayarlayın
bash
# Crontab düzenle
crontab -e

# Cron job'ları ekle
# Her 5 dakikada bir queue worker'ı çalıştır
*/5 * * * * php /var/www/html/api-master/CORN/queue_worker.php >> /var/www/html/api-master/logs/cron.log 2>&1

# Her saat başı memory consolidation
0 * * * * php /var/www/html/api-master/CORN/memory_consolidation.php >> /var/www/html/api-master/logs/cron.log 2>&1

# Her gün gece yarısı log rotasyonu
0 0 * * * php /var/www/html/api-master/CORN/log_rotator.php >> /var/www/html/api-master/logs/cron.log 2>&1

# Her gün saat 03:00'te learning model eğitimi
0 3 * * * php /var/www/html/api-master/CORN/train_models.php >> /var/www/html/api-master/logs/cron.log 2>&1
Adım 6: İzinleri Düzenleyin
bash
# Web sunucusu kullanıcısını öğren
WEB_USER=$(ps aux | grep -E "apache|nginx" | grep -v grep | head -1 | awk '{print $1}')

# İzinleri web sunucusu kullanıcısına ver
chown -R $WEB_USER:$WEB_USER /var/www/html/api-master/
chmod -R 755 /var/www/html/api-master/

# Yazılabilir dizinlere özel izin
chmod -R 777 /var/www/html/api-master/logs/
chmod -R 777 /var/www/html/api-master/cache/
chmod -R 777 /var/www/html/api-master/data/
chmod -R 777 /var/www/html/api-master/temp/
chmod -R 777 /var/www/html/api-master/learning/models/
chmod -R 777 /var/www/html/api-master/vector/indexes/
Adım 7: Test Edin
bash
# API test
curl http://localhost/api-master/api/v1/health

# Beklenen çıktı:
# {"status":"healthy","timestamp":"2024-01-15T10:30:00Z","components":{"api":"up"}}

# Admin panel test
curl http://localhost/api-master/panel

# Provider test
curl -X POST http://localhost/api-master/api/v1/providers/openai/test \
  -H "Authorization: Bearer your-api-key"
🔐 Güvenlik Ayarları
1. API Key Değiştirin
bash
# Yeni bir API key oluşturun
openssl rand -hex 32

# Çıktıyı config.json'daki api_key alanına yapıştırın
2. JWT Secret Oluşturun
bash
# Yeni JWT secret oluşturun
openssl rand -base64 32

# config.json'daki jwt_secret alanına yapıştırın
3. .htaccess veya Nginx ile koruyun
apache
# IP bazlı kısıtlama (sadece belirli IP'lerden erişim)
Require ip 192.168.1.100
Require ip 10.0.0.0/8

# Basic authentication (opsiyonel)
AuthType Basic
AuthName "API Master Admin"
AuthUserFile /var/www/html/api-master/.htpasswd
Require valid-user
4. HTTPS Kullanın
bash
# Let's Encrypt ile SSL sertifikası alın
certbot --apache -d api-master.domain.com

# Veya self-signed sertifika
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/api-master.key \
  -out /etc/ssl/certs/api-master.crt
🚨 Sorun Giderme
Sorun 1: "Permission denied" hataları
bash
# Çözüm: İzinleri düzeltin
chmod -R 777 logs cache data temp
Sorun 2: cURL hatası "SSL certificate problem"
bash
# Çözüm: CA sertifikalarını güncelleyin
sudo update-ca-certificates

# Veya geçici çözüm (güvenli değil)
# config.json'da 'verify_ssl': false
Sorun 3: "API key invalid" hatası
bash
# Çözüm: config.json'daki api_key'i kontrol edin
cat config/config.json | grep api_key

# Yeni API key oluşturun ve güncelleyin
Sorun 4: Bellek yetersiz hatası
bash
# Çözüm: PHP memory_limit artırın
# php.ini dosyasını düzenleyin
memory_limit = 512M

# Veya .htaccess'e ekleyin
php_value memory_limit 512M
Sorun 5: "Class not found" hataları
bash
# Çözüm: Autoloader'ı kontrol edin
ls -la CORE/autoloader.php

# Dosya yoksa yeniden oluşturun
php CORE/build_autoloader.php
✅ Kurulum Sonrası Kontrol Listesi
Tüm dosyalar doğru dizine kopyalandı mı?

config.json düzenlendi mi?

API key ve JWT secret oluşturuldu mu?

Dizin izinleri doğru ayarlandı mı?

Web sunucusu yeniden başlatıldı mı?

Cron job'lar eklendi mi?

Health endpoint çalışıyor mu?

Admin panel açılıyor mu?

En az bir provider test edildi mi?

Loglar yazılıyor mu?

Cache çalışıyor mu?

📞 Destek
Kurulum sırasında sorun yaşarsanız:

Logları kontrol edin: logs/error.log

PHP hata logları: /var/log/php_errors.log

Web sunucusu logları: /var/log/apache2/error.log

Dokümantasyonu okuyun: /docs/

Destek ekibine yazın: support@apimaster.com

🔄 Güncelleme
bash
# 1. Yedek alın
tar -czf api-master-backup-$(date +%Y%m%d).tar.gz /var/www/html/api-master/

# 2. Yeni dosyaları indirin
cd /var/www/html/api-master
git pull origin main

# 3. Migration'ları çalıştırın
php migrations/migrate.php

# 4. Cache'i temizleyin
rm -rf cache/*

# 5. Web sunucusunu yeniden başlatın
systemctl restart apache2  # veya nginx
🗑️ Kaldırma
bash
# 1. Cron job'ları temizleyin
crontab -e
# API Master ile ilgili satırları silin

# 2. Dosyaları silin
rm -rf /var/www/html/api-master/

# 3. Cache ve logları temizleyin (opsiyonel)
rm -rf /tmp/apimaster_*
Kurulum tamamlandı! 🎉

Artık API Master'ı kullanmaya başlayabilirsiniz:

Admin Paneli: http://your-domain.com/api-master/panel

API Dokümantasyonu: /docs/API_DOCS.md

Örnek Kodlar: /examples/