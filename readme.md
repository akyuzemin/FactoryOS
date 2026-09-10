# 🏭 FactoryOS

**Integrated Manufacturing Operations Platform**

FactoryOS; üretim, MES (Üretim Yürütme Sistemi), stok, depo, kalite kontrol, bakım & TPM, enerji, satın alma, çalışan yönetimi, OEE ve Andon operasyonlarını tek bir merkezden yönetmek amacıyla geliştirilmiş modüler ve genişletilebilir bir operasyon platformudur.

> **Uygulama Senaryosu:**  
> Proje, **Schmid Pekintaş** güneş paneli üretim fabrikasını simüle eden gerçekçi süreç mimarisi ve test/demo verileriyle geliştirilmiştir. Canlı operasyonel veriler, MES olayları ve cihaz telemetrileri sentetik modeller üzerinden simüle edilmektedir.

---

## 📑 İçindekiler

- [Proje Kapsamı ve Modüller](#-proje-kapsamı-ve-modüller)
- [Teknoloji Yığını](#-teknoloji-yığını)
- [Sistem Mimarisi](#-sistem-mimarisi)
- [Veritabanı Mimarisi](#-veritabanı-mimarisi)
- [Güvenlik ve Veri Bütünlüğü](#-güvenlik-ve-veri-bütünlüğü)
- [Test ve Doğrulama Yapısı](#-test-ve-doğrulama-yapısı)
- [Kurulum ve Başlangıç](#-kurulum-ve-başlangıç)

---

## 📦 Proje Kapsamı ve Modüller

FactoryOS, endüstriyel üretim tesislerinin ihtiyaç duyduğu temel operasyonel katmanları kapsayan modüllerden oluşur:

* **📦 Stok Yönetimi:** Hammadde, yarı mamul, sarf ve bitmiş ürün stok takibi, minimum/maksimum stok seviye kontrolleri, kritik stok alarmı ve dinamik stok hareket geçmişi.
* **🏢 Depo ve Lokasyon Yönetimi:** Çoklu depo ve lokasyon bazlı hammadde/ürün yerleşimi, depolar arası kontrollü transferler ve stok düzeltme/sayım işlemleri.
* **🏭 Üretim Yönetimi:** İş emirleri (Work Orders), ürün reçeteleri (BOM - Bill of Materials), rota ve operasyon adımları, anlık reçete bazlı hammadde tüketim hesaplamaları.
* **📡 MES Entegrasyonu:** Üretim hattı makinelerinden gelen olayların (Event Ingestion) anlık işlenmesi, asenkron background worker desteği, simülasyon motoru ve hat olay kayıtları.
* **📊 OEE ve Üretim Performansı:** Kullanılabilirlik (Availability), Performans (Performance) ve Kalite (Quality) bileşenleri üzerinden gerçek zamanlı OEE hesaplamaları ve hat bazlı verimlilik analizi.
* **🧪 Kalite Yönetimi:** Giriş kalite, proses kontrol ve son kalite kontrol istasyonları, hata tipi sınıflandırması, karantina süreçleri ve fire/hurda yönetimi.
* **🔧 Bakım ve TPM (Total Productive Maintenance):** Ekipman ve hat varlık yönetimi, arıza bildirimleri, periyodik bakım planları, açık iş emirleri ve MTBF/MTTR göstergeleri.
* **⚡ Enerji Yönetimi:** Sayaç bazlı elektrik tüketim takibi, GES (Güneş Enerjisi Santrali) üretim izleme, spesifik enerji tüketimi (SEC) ve enerji maliyet raporlaması.
* **🛒 Satın Alma:** Tedarikçi yönetimi, satın alma talepleri ve siparişlerinin onay akışı, mal kabul süreçleri ile entegre stok girişleri.
* **👷 Çalışan Yönetimi:** Vardiya, yetkinlik matrisi, operatör-hat eşleştirmeleri ve personel bazlı operasyonel izlenebilirlik.
* **📺 Andon / Canlı Fabrika İzleme:** Üretim hatlarının çalışma/duruş/arıza durumlarını görselleştiren, operatör müdahale çağrılarını yansıtan dijital fabrika panosu.
* **🔎 Lot/Batch ve Ürün Genealogy (Dijital Ürün Pasaportu):** Üretilen her güneş panelinin seri numarası üzerinden kullanılan hücre, EVA, cam ve ribbon partilerine kadar geriye dönük tam şecere (Traceability) takibi.
* **📈 Yönetici Dashboard ve Raporlama:** Kritik stok uyarıları, açık bakım emirleri, aktif üretim duruşları, KPI kartları ve operasyonel özet raporlar.
* **🔐 RBAC ve Yetkilendirme:** Rol tabanlı granüler izin mimarisi (Admin, Yönetici, Üretim Personeli, Depo Personeli, Kalite Uzmanı, Bakım Teknisyeni, Operatör).
* **🔑 API Token / Makine-MES Entegrasyonu:** Endüstriyel makineler ve harici servisler için SHA-256 hash'li Bearer token kimlik doğrulaması ve güvenli REST API uç noktaları.

---

## 💻 Teknoloji Yığını

FactoryOS, harici ağır framework veya kütüphane bağımlılıklarına ihtiyaç duymadan, saf (native) ve optimize edilmiş modern web teknolojileriyle inşa edilmiştir:

* **Backend:** PHP 8.2+ (Tip güvenli, OOP, MVC, Service Layer mimarisi)
* **Veritabanı:** MySQL 8.0+ (InnoDB ve mevcut legacy MyISAM tabloları, Foreign Key ilişkileri, JSON sütunları, UTF8mb4)
* **Frontend:** 
  * HTML5 & Vanilla ES6+ JavaScript (Harici JS framework bağımlılığı olmadan hafif ve hızlı arayüz)
  * Vanilla Modern CSS3 (`public/css/app.css` — Özel tasarım sistemi, CSS değişkenleri, Responsive Grid & Flexbox)
  * *Not: Projede Bootstrap veya harici CSS framework'ü kullanılmamış; hafiflik ve esneklik için bağımsız Vanilla CSS mimarisi tercih edilmiştir.*
* **Tipografi:** Google Fonts (Plus Jakarta Sans & Inter)
* **Sunucu & Ortam:** Apache / WampServer (URL Rewrite & RESTful routing)

---

## 🏛️ Sistem Mimarisi

Uygulama, temiz katmanlı mimari (Clean Layered Architecture) ve sorumlulukların ayrılığı (Separation of Concerns) prensipleri doğrultusunda tasarlanmıştır:

```text
HTTP Request / API Call
       │
       ▼
   [ Router ] ──────────────► URL ayrıştırma ve endpoint eşleştirme
       │
       ▼
 [ Middleware ] ────────────► AuthMiddleware, CsrfMiddleware, TokenAuthMiddleware, PermissionMiddleware
       │
       ▼
 [ Controller ] ────────────► İstek karşılama, girdi doğrulama, HTTP yanıt yönetimi
       │
       ▼
  [ Service ]  ────────────► İş kuralları, transaction yönetimi, MES event ingestion, OEE formülleri
       │
       ▼
   [ Model ]   ────────────► Veri erişim soyutlaması, SQL sorgu mantığı
       │
       ▼
   [ PDO / DB ] ────────────► Prepared Statements, Transaction (BEGIN/COMMIT/ROLLBACK)
       │
       ▼
    [ MySQL ]
```

### Temel Mimari Prensipleri:
* **MVC & Service Layer:** Kontrolcüler (Controller) yalnızca HTTP istek/yanıt akışını yönetir; karmaşık iş kuralları ve domain mantığı bağımsız Servis sınıflarında (`MaintenanceService`, `MesEventIngestionService`, `OeeCalculationService` vb.) toplanmıştır.
* **Dependency Injection:** Servis ve modeller, veritabanı bağlantısını (`PDO`) constructor üzerinden enjekte alarak gevşek bağlılık (loose coupling) sağlar.
* **İşlemsel Bütünlük (Transactional Workflows):** Üretim kaydı, reçete tüketimi ve stok düşümü gibi kritik süreçler veritabanı seviyesinde `BEGIN TRANSACTION`, `COMMIT` ve `ROLLBACK` blokları ile atomik olarak korunur.
* **Idempotency & Sequence Control:** MES olay akışlarında event_id tabanlı idempotency / mükerrer MES event kontrolü ve ardışık olay sıralama denetimleri uygulanmıştır.

---

## 🗄️ Veritabanı Mimarisi

Veritabanı yapısı, referans verileri ile operasyonel verileri birbirinden net şekilde ayıran 53 tablodan oluşur:

* **`database/schema.sql` (DDL):**
  * 53 tablonun tamamının şemasını, birincil/yabancı anahtar (PK/FK) ilişkilerini ve arama indekslerini sıfırdan oluşturur.
  * Hiçbir kullanıcı şifresi, hash veya operasyonel log kaydı barındırmaz.
* **`database/seed.sql` (Master Data):**
  * Yalnızca 24 adet referans ve master veriyi (kullanıcı rolleri, 40+ granüler izin tanımı, malzeme kategorileri, ölçü birimleri, standart reçeteler, solar hücre/panel tipleri, arıza nedenleri ve sentetik tedarikçiler) içerir.
  * Gerçek kullanıcı parolası veya canlı operasyonel transaction verisi içermez; güvenli başlangıç durumunu tanımlar.

---

## 🔒 Güvenlik ve Veri Bütünlüğü

* **Ortam Değişkeni Desteği:** Veritabanı bağlantı bilgileri `getenv()` ve `$_ENV` üzerinden dinamik olarak okunabilir.
* **Gizlilik Koruması:** `.env` dosyası Git takibinden hariç tutulmuştur (`.gitignore`); repo içinde yalnızca güvenli referans olan `.env.example` yer alır.
* **SQL Injection Koruması:** Veritabanı sorgularında PDO Prepared Statements kullanılarak parametrik sorgulama uygulanmaktadır.
* **XSS Savunması:** Kullanıcıdan gelen veya veritabanından okunan tüm dinamik içerikler View katmanında `htmlspecialchars()` ile sanitize edilir.
* **CSRF Koruması:** Durum değiştiren (POST) form ve isteklerde oturum bazlı CSRF token doğrulaması (`CsrfService`) uygulanmaktadır.
* **Granüler RBAC:** Kullanıcıların işlem yetkileri rol ve izin tablosu üzerinden dinamik olarak denetlenir.
* **API Güvenliği:** Makine ve dış entegrasyonlar için SHA-256 özetleme (hash) ile saklanan API token yapısı uygulanmıştır.
* **Denetim İzi (Audit Logging):** Kritik tablolardaki ekleme, güncelleme ve silme işlemleri kullanıcı, IP, modül ve eski/yeni değer farkları (`old_values`, `new_values` JSON) ile denetim günlüğüne kaydedilir.

---

## 🧪 Test ve Doğrulama Yapısı

Proje, temel iş akışlarını ve güvenlik katmanlarını doğrulamak üzere yapılandırılmış **29 adet** bağımsız otomatik test betiği içermektedir:

* **`tests/security/` (6 Test):** CSRF koruması, RBAC yetki izolasyonu, sızma/adversarial test senaryoları ve API token doğrulama testleri.
* **`tests/integration/` (8 Test):** MES hammadde tüketim/BOM entegrasyonu, canlı bitmiş ürün kaydı, üretim maliyet hesaplamaları ve MES simülasyon yaşam döngüsü testleri.
* **`tests/modules/` (15 Test):** OEE hesaplamaları, Andon olay tetikleyicileri, TPM/bakım modülü, enerji takibi, kalite kontrol ve yönetici dashboard testleri.

---

## 🚀 Kurulum ve Başlangıç

### Gereksinimler
* **İşletim Sistemi:** Windows (WampServer) veya Linux/macOS (Apache/Nginx)
* **PHP:** >= 8.2 (PDO, pdo_mysql, json, mbstring eklentileri aktif)
* **MySQL:** >= 8.0

### Adım Adım Kurulum (Windows / WampServer)

1. **Depoyu Klonlayın:**
   ```bash
   cd C:\wamp64\www
   git clone https://github.com/akyuzemin/FactoryOS.git
   cd FactoryOS
   ```

2. **Veritabanını Oluşturun:**
   MySQL istemcinizde veya phpMyAdmin üzerinde `stok_takip` adında bir veritabanı oluşturun:
   ```sql
   CREATE DATABASE stok_takip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Şema ve Başlangıç Verilerini İçe Aktarın:**
   ```bash
   # MySQL CLI üzerinden (WAMP varsayılanı boş root parolası):
   mysql -u root stok_takip < database/schema.sql
   mysql -u root stok_takip < database/seed.sql
   ```
   *Alternatif olarak phpMyAdmin arayüzünden `stok_takip` veritabanına `database/schema.sql` ve ardından `database/seed.sql` dosyalarını içe aktarabilirsiniz.*

4. **Konfigürasyon:**
   Dilerseniz kök dizinde `.env.example` dosyasını `.env` olarak kopyalayarak veritabanı bilgilerinizi özelleştirin:
   ```ini
   APP_ENV=local
   APP_DEBUG=true
   DB_HOST=localhost
   DB_NAME=stok_takip
   DB_USER=root
   DB_PASS=
   ```
   *(Varsayılan `config/database.php` dosyası, `.env` bulunmadığında doğrudan lokal WampServer `localhost` / `root` / boş şifre ayarlarına fallback yapar.)*

5. **Uygulamayı Çalıştırın:**
   Tarayıcınızdan aşağıdaki adresi ziyaret edin:
   ```text
   http://localhost/stok-takip/public/
   ```

---

## 📄 Lisans

Bu proje eğitim, portföy ve konsept geliştirme amacıyla hazırlanmıştır. Repository içerisinde harici bir açık kaynak lisans dosyası tanımlanmamış olup tüm hakları saklıdır.

