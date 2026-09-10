# Stok Takip & MES Sistemi — Kapsamlı Dosya ve Mimari Rehberi

> Bu doküman, projenin mevcut dosya yapısını, katman sorumluluklarını, modül ilişkilerini ve sorun giderme yollarını hızlıca kavramak ve bakımını kolaylaştırmak amacıyla hazırlanmıştır.

---

## 1. GENEL MİMARİ VE ÇALIŞMA PRENSİBİ

Proje, framework bağımsız (Vanilla PHP 8.2), native MVC mimarisine sahip, yüksek performanslı bir kurumsal **Stok, Depo, Üretim, MES, OEE, Enerji, Bakım, Satın Alma ve İK Yönetim Platformu**dur.

```
┌─────────────────────────────────────────────────────────────┐
│                    HTTP Request (public/index.php)          │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                     app/Router.php (routes/web.php)         │
│          + app/Middleware/AuthMiddleware (RBAC Güvenliği)   │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                       CONTROLLER KATMANI                    │
│   (Request parse, validation, orchestration, view binding)   │
└──────────────────┬──────────────────────┬───────────────────┘
                   │                      │
                   ▼                      ▼
┌───────────────────────────┐  ┌──────────────────────────────┐
│       SERVICE KATMANI     │  │        MODEL KATMANI         │
│ (İş mantığı, formüller,   │  │ (Veritabanı CRUD sorguları,  │
│  transaksiyonlar, event)  │  │  filtreler, master lookup)   │
└─────────────┬─────────────┘  └──────────────┬───────────────┘
              │                               │
              └───────────────┬───────────────┘
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                       VIEW KATMANI                          │
│        (HTML, Dashboard widget'ları, Chart.js, Modal'lar)   │
└─────────────────────────────────────────────────────────────┘
```

> [!IMPORTANT]
> **Mimari Kural:** Bu projede harici bir Autoloader (Composer/PSR-4), Repository, DTO veya Entity katmanı **bulunmamaktadır**. Veri erişimi doğrudan `Model` sınıfları içinde PDO ile yönetilir. İş mantığı ve karmaşık formüller `Service` sınıflarında, HTTP girdi/çıktı yönetimi ise `Controller` katmanındadır.

---

## 2. KÖK DİZİN YAPISI (ROOT DIRECTORY)

```
stok-takip/
├── app/          # Uygulama çekirdeği (Controllers, Middleware, Models, Services, Router)
├── config/       # Veritabanı PDO konfigürasyonu
├── database/     # Şema (DDL) ve başlangıç master verileri (seed)
├── docs/         # Mimari kılavuzlar, rehberler ve yol haritası
├── public/       # Web sunucusunun dışa açık kök dizini (index.php, CSS, JS)
├── routes/       # Uygulamanın 153 route ve RBAC yetki haritası (web.php)
├── scripts/      # MES arka plan worker'ları ve geliştirici yardımcı araçları
├── storage/      # Sistem logları ve yüklenen ekler/barkodlar
├── tests/        # Güvenlik, entegrasyon ve modül doğrulama testleri
├── views/        # 34 modüle ait HTML view arayüz şablonları
└── readme.md     # Proje tanıtım ve genel bilgi belgesi
```

| Klasör / Dosya | Amacı | İçeriği | Ne Zaman Müdahale Edilir? |
|---|---|---|---|
| [`app/`](file:///C:/wamp64/www/stok-takip/app) | Uygulama mantığı | Controller, Model, Service, Middleware ve Router | Yeni bir sayfa, iş kuralı veya DB sorgusu eklendiğinde |
| [`config/`](file:///C:/wamp64/www/stok-takip/config) | Sistem ayarları | [`database.php`](file:///C:/wamp64/www/stok-takip/config/database.php) (PDO bağlantısı) | DB kullanıcı adı, şifre veya host değiştiğinde |
| [`database/`](file:///C:/wamp64/www/stok-takip/database) | Veritabanı DDL/DML | [`schema.sql`](file:///C:/wamp64/www/stok-takip/database/schema.sql) (53 tablo) ve [`seed.sql`](file:///C:/wamp64/www/stok-takip/database/seed.sql) | Sıfırdan kurulum veya yeni tablo/izin eklendiğinde |
| [`docs/`](file:///C:/wamp64/www/stok-takip/docs) | Dokümantasyon | Proje rehberleri ve yol haritası | Mimari değişikliklerde veya bilgi güncellemesinde |
| [`public/`](file:///C:/wamp64/www/stok-takip/public) | Giriş & Statik Varlıklar | `index.php`, `css/app.css` (2.853 satır) | Bootstrap akışı veya genel tasarım/stil değişikliklerinde |
| [`routes/`](file:///C:/wamp64/www/stok-takip/routes) | URL Yönlendirme | [`web.php`](file:///C:/wamp64/www/stok-takip/routes/web.php) (153 route) | Yeni endpoint veya rota izin eşleşmesi eklendiğinde |
| [`scripts/`](file:///C:/wamp64/www/stok-takip/scripts) | Arka Plan & Araçlar | `mes_worker.php`, `dev/` test araçları | CLI simülasyonları veya arka plan worker yönetiminde |
| [`storage/`](file:///C:/wamp64/www/stok-takip/storage) | Runtime Depolama | `logs/`, `uploads/` | Log inceleme veya dosya yükleme yollarında |
| [`tests/`](file:///C:/wamp64/www/stok-takip/tests) | Test Paketleri | 29 adet güvenlik, entegrasyon ve modül testi | Kod refactor veya modül teslim doğrulamalarında |
| [`views/`](file:///C:/wamp64/www/stok-takip/views) | Kullanıcı Arayüzü | 79 adet PHP şablonu (HTML + Chart.js) | Ekran tasarımı, formlar veya tablo görünümlerinde |

---

## 3. APP KATMANI VE SORUMLULUKLARI

* **`app/Router.php`:** HTTP method (`GET`/`POST`) ve URI'yi eşleştirir, rota parametrelerini ayıklar ve `AuthMiddleware` üzerinden Controller'ı tetikler.
* **`app/Middleware/AuthMiddleware.php`:** Oturum kontrolü (`$_SESSION['user_id']`), aktif kullanıcı durumu, CSRF güvenliği ve RBAC izinlerini (`permissions`) doğrular.
* **`app/Controllers/` (37 Adet):** HTTP isteklerini karşılar, parametreleri doğrular, Model/Service metodlarını çağırır, flash mesajları oluşturur ve View şablonunu require eder.
* **`app/Models/` (25 Adet):** Veritabanı sorgularını (PDO SQL), filtreleri, sayfalama (pagination) hesaplarını ve DB seviyesi veri doğrulamalarını yürütür.
* **`app/Services/` (24 Adet):** OEE hesaplamaları, MES simülasyon döngüleri, reçete bazlı stok tüketimi, audit loglama ve Excel dışa aktarma gibi ağır iş kurallarını yürütür.

---

## 4. CONTROLLER LİSTESİ VE MODÜL DAĞILIMI (37 CONTROLLER)

| Controller Dosyası | Modül | Temel Görevi | Örnek Route'lar |
|---|---|---|---|
| [`AdminDashboardController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/AdminDashboardController.php) | Yönetici Paneli | Üst düzey KPI'lar, fabrika genel özetleri | `GET /admin/dashboard` |
| [`AndonController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/AndonController.php) | Andon Paneli | Üretim hatlarının canlı durum ekranı ve API | `GET /andon`, `GET /andon/api` |
| [`ApiTokenController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/ApiTokenController.php) | API Güvenliği | API anahtarı üretme, listeleme, iptal | `GET /api-tokens`, `POST /api-tokens/create` |
| [`AuditController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/AuditController.php) | Sistem Denetimi | Sistem aktivite ve değişiklik logları | `GET /audit-logs` |
| [`AuthController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/AuthController.php) | Kimlik Doğrulama | Giriş (Login), çıkış (Logout), session başlatma | `GET /login`, `POST /login`, `GET /logout` |
| [`DashboardController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/DashboardController.php) | Ana Panel | Fabrika genel stok ve üretim göstergeleri | `GET /` |
| [`EmployeeController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/EmployeeController.php) | İK & Personel | Çalışan CRUD, departman/unvan atamaları | `GET /employees`, `POST /employees/store` |
| [`EmployeeDashboardController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/EmployeeDashboardController.php) | İK Dashboard | Personel dağılımı, departman istatistikleri | `GET /employees/dashboard` |
| [`EnergyAlertController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/EnergyAlertController.php) | Enerji Uyarıları | Aşırı tüketim ve reaktif güç alarmları | `GET /energy/alerts` |
| [`EnergyConsumptionController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/EnergyConsumptionController.php) | Enerji Tüketimi | Sayaç ve hat bazlı elektrik tüketim raporları | `GET /energy/consumption` |
| [`EnergyCostController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/EnergyCostController.php) | Enerji Maliyeti | Tarife bazlı birim ve toplam elektrik maliyeti | `GET /energy/cost` |
| [`EnergyDashboardController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/EnergyDashboardController.php) | Enerji Paneli | Canlı trafo yükleri, GES üretimi özetleri | `GET /energy` |
| [`EnergyGesController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/EnergyGesController.php) | GES (Güneş Santrali) | Güneş santrali anlık üretim ve inverter verisi | `GET /energy/ges` |
| [`EnergyMeterController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/EnergyMeterController.php) | Sayaç Yönetimi | Modbus enerji sayaçlarının tanımları | `GET /energy/meters` |
| [`EnergyProductionController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/EnergyProductionController.php) | Enerji/Üretim İlişkisi| Üretilen parça başına harcanan kWh | `GET /energy/production` |
| [`EnergyReportController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/EnergyReportController.php) | Enerji Raporları | Dönemsel enerji analizleri ve dışa aktarma | `GET /energy/reports` |
| [`FinishedGoodsController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/FinishedGoodsController.php) | Mamul Ambarı | Üretimi tamamlanmış güneş panelleri listesi | `GET /finished-goods` |
| [`InventoryController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/InventoryController.php) | Demirbaş & Envanter | Şirket demirbaş varlıkları ve zimmet hareketleri | `GET /inventory`, `POST /inventory/store` |
| [`LocationController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/LocationController.php) | Depo Lokasyonları | Depo içi raf/göz/koridor tanımları | `GET /locations`, `POST /locations/store` |
| [`MaintenanceController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/MaintenanceController.php) | Bakım Yönetimi | Bakım iş emirleri, arıza kaydı, parça kullanımı | `GET /maintenance`, `POST /maintenance/store` |
| [`MaterialController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/MaterialController.php) | Malzeme Kartları | Hammadde, yarı mamul, yedek parça kartları | `GET /materials`, `POST /materials/store` |
| [`MesController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/MesController.php) | MES Üretim Yürütme | Operatör ekranı, istasyon olayları, simülasyon | `GET /mes`, `POST /mes/event` (21 route) |
| [`OeeController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/OeeController.php) | OEE & Hat Performansı | Hat bazlı OEE, duruş başlatma/bitirme, Pareto | `GET /oee`, `GET /oee/api/live` |
| [`PortalController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/PortalController.php) | Dış Portal | Tedarikçi/müşteri dış erişim arayüzü | `GET /portal` |
| [`ProductionController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/ProductionController.php) | Klasik Üretim | Standart üretim emri açma ve tamamlama | `GET /production`, `POST /production/store` |
| [`PurchaseOrderController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/PurchaseOrderController.php) | Satın Alma Siparişi | PO oluşturma, tedarikçiye iletme, onaylama | `GET /purchase-orders`, `POST /purchase-orders/store` |
| [`PurchaseReceiptController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/PurchaseReceiptController.php) | Mal Kabul | İrsaliye girişi, kalite kontrol, stoğa aktarım | `GET /purchase-receipts`, `POST /purchase-receipts/store` |
| [`PurchaseRequestController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/PurchaseRequestController.php) | Satın Alma Talebi | PR oluşturma, onay zinciri, bütçe doğrulama | `GET /purchase-requests`, `POST /purchase-requests/store` |
| [`RecipeController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/RecipeController.php) | Reçeteler (BOM) | Ürün ağaçları, hammadde tüketim oranları | `GET /recipes`, `POST /recipes/store` |
| [`ReportController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/ReportController.php) | Raporlama | Stok, üretim, fire ve sevkiyat raporları | `GET /reports` |
| [`RolePermissionController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/RolePermissionController.php) | Rol & Yetki (RBAC) | Rol oluşturma, 56 yetkinin matris yönetimi | `GET /role-permissions` |
| [`ShipmentController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/ShipmentController.php) | Sevkiyat | Müşteri sevkiyatı hazırlama, panel eşleme | `GET /shipments`, `POST /shipments/store` |
| [`StockController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/StockController.php) | Stok İşlemleri | Manuel stok girişi, çıkışı, transfer, sayım | `GET /stock`, `POST /stock/in` |
| [`StockMovementController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/StockMovementController.php) | Stok Hareketleri | Tüm ambar hareket geçmişi ve filtreleme | `GET /stock-movements` |
| [`SupplierController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/SupplierController.php) | Tedarikçiler | Tedarikçi firma tanımları ve iletişim bilgileri | `GET /suppliers`, `POST /suppliers/store` |
| [`UserController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/UserController.php) | Kullanıcılar | Sistem kullanıcıları, şifre belirleme, rol atama | `GET /users`, `POST /users/store` |
| [`WarehouseController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/WarehouseController.php) | Depolar | Ana ambar, hammadde ve mamul depoları | `GET /warehouses`, `POST /warehouses/store` |

---

## 5. MODEL KATMANI (25 MODEL)

Tüm modeller veritabanı CRUD operasyonlarını, filtreleri, sayfalama ve veri doğrulama kurallarını barındırır.

* **[`PurchaseOrder.php`](file:///C:/wamp64/www/stok-takip/app/Models/PurchaseOrder.php) (1.354 satır):** Satın alma sipariş state machine'i (`DRAFT` -> `SENT` -> `CONFIRMED` -> `RECEIVED`), PR'dan otomatik PO üretme transaksiyonu ve sipariş kalemleri.
* **[`PurchaseRequest.php`](file:///C:/wamp64/www/stok-takip/app/Models/PurchaseRequest.php) (1.040 satır):** Talep yaşam döngüsü (`DRAFT` -> `SUBMITTED` -> `APPROVED` -> `ORDERED`), onay adımları, bütçe ve kalem kontrolleri.
* **[`PurchaseReceipt.php`](file:///C:/wamp64/www/stok-takip/app/Models/PurchaseReceipt.php):** Mal kabul irsaliyeleri, lot numaraları ve otomatik stok bakiye artış tetikleyicisi.
* **[`Inventory.php`](file:///C:/wamp64/www/stok-takip/app/Models/Inventory.php) (839 satır):** Şirket demirbaşları, amortisman süreleri, zimmet ve transfer kayıtları.
* **[`Mes.php`](file:///C:/wamp64/www/stok-takip/app/Models/Mes.php) (681 satır):** MES iş emirleri, istasyon olay kayıtları, canlı hat durumları ve panel serileştirme.
* **[`Stock.php`](file:///C:/wamp64/www/stok-takip/app/Models/Stock.php) & [`StockMovement.php`](file:///C:/wamp64/www/stok-takip/app/Models/StockMovement.php):** Stok bakiyeleri (`stock_balances`) ve hareket denetim izi (`stock_movements`).
* **[`Employee.php`](file:///C:/wamp64/www/stok-takip/app/Models/Employee.php) & [`EmployeeDashboard.php`](file:///C:/wamp64/www/stok-takip/app/Models/EmployeeDashboard.php):** Personel kartları, departman/unvan ve vardiya atamaları.
* **[`EnergyDashboard.php`](file:///C:/wamp64/www/stok-takip/app/Models/EnergyDashboard.php) & [`EnergyCost.php`](file:///C:/wamp64/www/stok-takip/app/Models/EnergyCost.php):** Trafo sayaç okumaları, GES üretim logları ve elektrik maliyet matrisleri.
* **[`PanelUnit.php`](file:///C:/wamp64/www/stok-takip/app/Models/PanelUnit.php):** Üretilen güneş panellerinin seri numarası, elektriksel test değerleri (Wp, Voc, Isc, FF) ve soyağacı (genealogy).
* **[`Material.php`](file:///C:/wamp64/www/stok-takip/app/Models/Material.php), [`Recipe.php`](file:///C:/wamp64/www/stok-takip/app/Models/Recipe.php), [`Supplier.php`](file:///C:/wamp64/www/stok-takip/app/Models/Supplier.php), [`Warehouse.php`](file:///C:/wamp64/www/stok-takip/app/Models/Warehouse.php), [`Location.php`](file:///C:/wamp64/www/stok-takip/app/Models/Location.php), [`User.php`](file:///C:/wamp64/www/stok-takip/app/Models/User.php), [`RolePermission.php`](file:///C:/wamp64/www/stok-takip/app/Models/RolePermission.php), [`Shipment.php`](file:///C:/wamp64/www/stok-takip/app/Models/Shipment.php), [`Report.php`](file:///C:/wamp64/www/stok-takip/app/Models/Report.php), [`AdminDashboard.php`](file:///C:/wamp64/www/stok-takip/app/Models/AdminDashboard.php), [`Dashboard.php`](file:///C:/wamp64/www/stok-takip/app/Models/Dashboard.php), [`Production.php`](file:///C:/wamp64/www/stok-takip/app/Models/Production.php), [`EnergyAlert.php`](file:///C:/wamp64/www/stok-takip/app/Models/EnergyAlert.php):** İlgili modüllerin veri erişim modelleri.

---

## 6. SERVİS KATMANI (24 SERVİS)

Servisler, birden fazla modelin koordinasyonunu, matematiksel formülleri ve asenkron arka plan görevlerini yönetir.

| Servis Dosyası | Temel Görevi |
|---|---|
| [`MesSimulationService.php`](file:///C:/wamp64/www/stok-takip/app/Services/MesSimulationService.php) | Üretim hattındaki istasyonların sanal çevrim sürelerini ve sensör olaylarını simüle eder. |
| [`MesEventIngestionService.php`](file:///C:/wamp64/www/stok-takip/app/Services/MesEventIngestionService.php) | İstasyonlardan gelen ham olayları (String, Laminasyon, Flaş Test) doğrular ve veritabanına işler. |
| [`MesProductionIntegrationService.php`](file:///C:/wamp64/www/stok-takip/app/Services/MesProductionIntegrationService.php) | Flaş testten başarıyla geçen panelleri mamul stoğuna (`panel_units` ve `stock_balances`) otomatik aktarır. |
| [`ProductionStockService.php`](file:///C:/wamp64/www/stok-takip/app/Services/ProductionStockService.php) | Üretim tamamlandığında reçetede (BOM) tanımlı hammaddeleri depodan otomatik düşer. |
| [`OeeCalculationService.php`](file:///C:/wamp64/www/stok-takip/app/Services/OeeCalculationService.php) | Kullanılabilirlik (A), Performans (P), Kalite (Q) ve Genel Ekipman Verimliliği (OEE) formüllerini hesaplar. |
| [`DowntimeManagementService.php`](file:///C:/wamp64/www/stok-takip/app/Services/DowntimeManagementService.php) | Üretim duruşlarının açılması, kapatılması, neden analizi ve Pareto dağılımını hesaplar. |
| [`MaintenanceService.php`](file:///C:/wamp64/www/stok-takip/app/Services/MaintenanceService.php) | Periyodik bakım takvimi, iş emri kapatma, MTTR ve MTBF güvenilirlik metriklerini hesaplar. |
| [`PermissionService.php`](file:///C:/wamp64/www/stok-takip/app/Services/PermissionService.php) | Kullanıcının rollerine göre 56 izinden hangilerine sahip olduğunu kontrol eder (`hasPermission`). |
| [`CsrfService.php`](file:///C:/wamp64/www/stok-takip/app/Services/CsrfService.php) | Formlarda ve Ajax isteklerinde CSRF token üretimi ve doğrulamasını yürütür. |
| [`AuditService.php`](file:///C:/wamp64/www/stok-takip/app/Services/AuditService.php) | Kritik kayıt güncellemelerinde eski ve yeni değerleri JSON formatında `audit_logs` tablosuna yazar. |
| [`ApiAuthService.php`](file:///C:/wamp64/www/stok-takip/app/Services/ApiAuthService.php) | Harici sistemlerden gelen Bearer API tokenlarını SHA-256 ile doğrular. |
| [`XlsxExportService.php`](file:///C:/wamp64/www/stok-takip/app/Services/XlsxExportService.php) | Rapor ekranlarındaki verileri Excel uyumlu formatta dışa aktarır. |
| [`AndonService.php`](file:///C:/wamp64/www/stok-takip/app/Services/AndonService.php) | Canlı Andon TV ekranı için hat durumlarını ve günlük üretim sayılarını derler. |
| [`EnergyAlertService.php`](file:///C:/wamp64/www/stok-takip/app/Services/EnergyAlertService.php) | Sayaç tüketimlerinde eşik değer aşıldığında sistem uyarısı oluşturur. |
| [`EnergyCostService.php`](file:///C:/wamp64/www/stok-takip/app/Services/EnergyCostService.php) | Gündüz/Puant/Gece tarifelerine göre elektrik faturası maliyetlerini hesaplar. |
| [`EnergyProductionLinkService.php`](file:///C:/wamp64/www/stok-takip/app/Services/EnergyProductionLinkService.php) | Hat bazlı enerji tüketimi ile üretilen panel sayısını eşleştirir. |
| [`FactoryOverviewService.php`](file:///C:/wamp64/www/stok-takip/app/Services/FactoryOverviewService.php) | Fabrika genel özet dashboard kartları için çoklu modül verilerini birleştirir. |
| [`MesBomConsumptionService.php`](file:///C:/wamp64/www/stok-takip/app/Services/MesBomConsumptionService.php) | MES iş emri seviyesinde gerçek ve teorik malzeme tüketim farkını raporlar. |
| [`MesCostService.php`](file:///C:/wamp64/www/stok-takip/app/Services/MesCostService.php) | Üretilen panel başına hammadde + enerji + işçilik birim maliyetini hesaplar. |
| [`MesEventFormatterService.php`](file:///C:/wamp64/www/stok-takip/app/Services/MesEventFormatterService.php) | İstasyon olay loglarını UI'da okunabilir Türkçe mesajlara dönüştürür. |
| [`MesEventValidationService.php`](file:///C:/wamp64/www/stok-takip/app/Services/MesEventValidationService.php) | İstasyon olay verilerinin şema ve tip doğruluğunu denetler. |
| [`MesWorkerService.php`](file:///C:/wamp64/www/stok-takip/app/Services/MesWorkerService.php) | Arka planda çalışan MES simülasyon adımlarını CLI üzerinden yürütür. |
| [`QrCodeService.php`](file:///C:/wamp64/www/stok-takip/app/Services/QrCodeService.php) | Panel ve malzeme etiketleri için QR/Barkod metin formatını hazırlar. |
| [`RealMesAdapterService.php`](file:///C:/wamp64/www/stok-takip/app/Services/RealMesAdapterService.php) | Fiziksel PLC/SCADA sistemlerinden gelen verileri simülatör yerine sisteme bağlar. |

---

## 7. VERİTABANI YAPISI (`database/`)

Veritabanı 53 tablodan oluşur. İlişkisel bütünlük ve transaksiyon gerektiren tablolar `InnoDB`, okuma ağırlıklı veya bağımsız tablolar `MyISAM` motorunu kullanır.

* **[`database/schema.sql`](file:///C:/wamp64/www/stok-takip/database/schema.sql) (53 Tablo):** Saf DDL tanımlarını içerir. Tablolar Foreign Key (FK) bağımlılık hiyerarşisine (Topological Sort) göre dizilmiştir.
* **[`database/seed.sql`](file:///C:/wamp64/www/stok-takip/database/seed.sql) (24 Tablo):** Sistemin sıfırdan ayağa kalkması için gereken 7 rol, 56 RBAC izni, 10 departman, 39 pozisyon, birimler, kategoriler, depolar, sayaçlar, hatlar ve reçeteleri içerir.
* **Runtime Veri Ayrımı:** Kullanıcı hesapları (`users`), API anahtarları (`api_tokens`), 14.000+ stok hareketi, 100 çalışan ve geçmiş üretim/satın alma kayıtları `seed.sql` içine **konulmaz** (Sıfır kurulum temizliği).

---

## 8. MODÜL VE DOSYA EŞLEŞTİRME TABLOSU

| Modül | UI / View | Controller | Model | Service | Ana Tablolar |
|---|---|---|---|---|---|
| **Dashboard** | `views/dashboard/`, `views/admin/` | `DashboardController`, `AdminDashboardController` | `Dashboard`, `AdminDashboard` | `FactoryOverviewService` | `stock_balances`, `mes_work_orders`, `production_lines` |
| **Stok & Ambar** | `views/stock/`, `views/stock-movements/` | `StockController`, `StockMovementController` | `Stock`, `StockMovement` | `ProductionStockService` | `stock_balances`, `stock_movements`, `materials` |
| **Depo & Lokasyon** | `views/warehouses/`, `views/locations/` | `WarehouseController`, `LocationController` | `Warehouse`, `Location` | — | `warehouses`, `locations` |
| **Malzeme & Reçete** | `views/materials/`, `views/recipes/` | `MaterialController`, `RecipeController` | `Material`, `Recipe` | — | `materials`, `material_suppliers`, `recipes`, `recipe_items` |
| **Satın Alma** | `views/purchase-requests/`, `views/purchase-orders/`, `views/purchase-receipts/` | `PurchaseRequestController`, `PurchaseOrderController`, `PurchaseReceiptController` | `PurchaseRequest`, `PurchaseOrder`, `PurchaseReceipt` | — | `purchase_requests`, `purchase_orders`, `purchase_receipts` |
| **MES (Üretim)** | `views/mes/`, `views/production/` | `MesController`, `ProductionController` | `Mes`, `Production`, `PanelUnit` | `MesSimulationService`, `MesEventIngestionService` | `mes_work_orders`, `mes_production_events`, `panel_units` |
| **OEE & Duruş** | `views/oee/` | `OeeController` | `Mes`, `Production` | `OeeCalculationService`, `DowntimeManagementService` | `line_downtimes`, `downtime_reasons`, `line_cycle_times` |
| **Andon** | `views/andon/` | `AndonController` | `Mes` | `AndonService` | `production_lines`, `mes_work_orders` |
| **Bakım Yönetimi** | `views/maintenance/` | `MaintenanceController` | `Inventory` | `MaintenanceService` | `maintenance_assets`, `maintenance_plans`, `maintenance_work_orders` |
| **Enerji Yönetimi** | `views/energy/`, `views/energy-dashboard/`, `views/energy-alerts/` | `EnergyDashboardController`, `EnergyCostController`, `EnergyAlertController` vb. | `EnergyDashboard`, `EnergyCost`, `EnergyAlert` | `EnergyCostService`, `EnergyAlertService` | `energy_meters`, `energy_readings`, `energy_tariffs`, `energy_alerts` |
| **Envanter & Demirbaş** | `views/inventory/` | `InventoryController` | `Inventory` | — | `inventory_assets`, `inventory_asset_movements`, `inventory_categories` |
| **İK & Personel** | `views/employees/` | `EmployeeController`, `EmployeeDashboardController` | `Employee`, `EmployeeDashboard` | — | `employees`, `employee_shifts`, `departments`, `positions` |
| **Sevkiyat** | `views/shipments/`, `views/finished-goods/` | `ShipmentController`, `FinishedGoodsController` | `Shipment`, `PanelUnit` | — | `shipments`, `shipment_items`, `panel_units` |
| **RBAC & Yetkiler** | `views/role-permissions/`, `views/users/` | `RolePermissionController`, `UserController` | `RolePermission`, `User` | `PermissionService` | `roles`, `permissions`, `role_permissions`, `users` |
| **Audit & Log** | `views/audit/` | `AuditController` | — | `AuditService` | `audit_logs` |
| **API & Entegrasyon** | `views/api_tokens/` | `ApiTokenController` | — | `ApiAuthService` | `api_tokens` |

---

## 9. SORUN GİDERME REHBERİ (NEREYE BAKMALIYIM?)

* **Stok Bakiyesi veya Hareketi Hatalıysa:**
  1. [`app/Models/Stock.php`](file:///C:/wamp64/www/stok-takip/app/Models/Stock.php) ve [`app/Services/ProductionStockService.php`](file:///C:/wamp64/www/stok-takip/app/Services/ProductionStockService.php) dosyalarını inceleyin.
  2. Veritabanında `stock_balances` ve `stock_movements` tablolarını kontrol edin.
* **Satın Alma Talebi (PR) Onaylanmıyor veya Kaydedilmiyorsa:**
  1. [`app/Controllers/PurchaseRequestController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/PurchaseRequestController.php) ve [`app/Models/PurchaseRequest.php`](file:///C:/wamp64/www/stok-takip/app/Models/PurchaseRequest.php) dosyalarını inceleyin.
  2. Durum geçiş matrisi (`STATUS_TRANSITIONS`) ve `purchase_requests.status` değerini kontrol edin.
* **Satın Alma Siparişi (PO) veya PR -> PO Dönüşümünde Sorun Varsa:**
  1. [`app/Controllers/PurchaseOrderController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/PurchaseOrderController.php) ve [`app/Models/PurchaseOrder.php`](file:///C:/wamp64/www/stok-takip/app/Models/PurchaseOrder.php) (`createFromPurchaseRequest`) metodunu inceleyin.
* **MES Üretim Simülasyonu İlerlemıyorsa:**
  1. [`app/Services/MesSimulationService.php`](file:///C:/wamp64/www/stok-takip/app/Services/MesSimulationService.php) ve [`scripts/mes_worker.php`](file:///C:/wamp64/www/stok-takip/scripts/mes_worker.php) dosyalarını kontrol edin.
  2. `mes_simulations` tablosundaki `status` değerinin `'RUNNING'` olduğunu doğrulayın.
* **OEE Oranı veya Duruş Kaydı Yanlışsa:**
  1. [`app/Controllers/OeeController.php`](file:///C:/wamp64/www/stok-takip/app/Controllers/OeeController.php), [`app/Services/OeeCalculationService.php`](file:///C:/wamp64/www/stok-takip/app/Services/OeeCalculationService.php) ve [`app/Services/DowntimeManagementService.php`](file:///C:/wamp64/www/stok-takip/app/Services/DowntimeManagementService.php) dosyalarını inceleyin.
* **Kullanıcı Menüyü veya Sayfayı Göremiyorsa (Yetki Sorunu):**
  1. [`app/Services/PermissionService.php`](file:///C:/wamp64/www/stok-takip/app/Services/PermissionService.php) ve [`views/layouts/sidebar.php`](file:///C:/wamp64/www/stok-takip/views/layouts/sidebar.php) dosyasındaki izin dizisini kontrol edin.
  2. `role_permissions` ve `permissions` tablolarındaki eşleşmeyi doğrulayın.

---

## 10. GELİŞTİRİCİ KURALLARI (KOD YAZARKEN DİKKAT EDİLECEKLER)

1. **Controller'a Doğrudan SQL Yazmayın:** Tüm veritabanı sorguları (`SELECT`, `INSERT`, `UPDATE`, `DELETE`) ilgili `Model` sınıfı içerisine yazılmalı, Controller yalnızca modeli çağırmalıdır.
2. **Mimariyi Sade Tutun:** Projede bulunmayan harici katmanları (Repository, DTO, Entity, Interface, Composer/PSR-4) sırf "kurumsal" görünmesi için eklemeyin.
3. **Prepared Statement Kullanın:** Tüm SQL sorgularında kullanıcı parametrelerini PDO `:parametre` bağlamasıyla yürütün; doğrudan string birleştirme yapmayın.
4. **İsimlendirme Standardı:** PHP sınıf ve metod adlarını İngilizce CamelCase (`PurchaseOrder`, `generateOrderNo`), kullanıcı arayüzü (UI) metinlerini ise %100 Türkçe tutun.
5. **Güvenlik:** Tüm `POST` formlarında `CsrfService::getToken()` ve `CsrfService::validateToken()` kullanın.
6. **State Machine Bütünlüğü:** Sipariş, talep veya iş emri durumlarını doğrudan SQL ile güncellemek yerine Model'deki onaylı durum geçiş metodlarını (`submit`, `approve`, `cancel`) kullanın.

---

## 11. PROJEYİ ÖĞRENME SIRASI (YOL HARİTASI)

Projeyi sıfırdan inşa etmiş bir mühendis gibi kavramak için dosyaları şu sırayla inceleyin:

1. [`public/index.php`](file:///C:/wamp64/www/stok-takip/public/index.php) — Projenin nasıl ayağa kalktığını, session ve hata ayarlarını anlayın.
2. [`config/database.php`](file:///C:/wamp64/www/stok-takip/config/database.php) — PDO MySQL bağlantısını ve UTF-8 Türkçe karakter seti ayarlarını görün.
3. [`app/Router.php`](file:///C:/wamp64/www/stok-takip/app/Router.php) — URL isteklerinin nasıl çözümlendiğini öğrenin.
4. [`app/Middleware/AuthMiddleware.php`](file:///C:/wamp64/www/stok-takip/app/Middleware/AuthMiddleware.php) — Oturum ve 56 RBAC izninin nasıl denetlendiğini kavrayın.
5. [`routes/web.php`](file:///C:/wamp64/www/stok-takip/routes/web.php) — Sistemin 153 rotasını ve hangi iznin hangi Controller'a bağlı olduğunu inceleyin.
6. [`app/Models/User.php`](file:///C:/wamp64/www/stok-takip/app/Models/User.php) & [`app/Services/PermissionService.php`](file:///C:/wamp64/www/stok-takip/app/Services/PermissionService.php) — Rol ve yetki hiyerarşisini çözün.
7. [`app/Models/Material.php`](file:///C:/wamp64/www/stok-takip/app/Models/Material.php) — Hammadde, mamul ve yedek parça yapısını anlayın.
8. [`app/Models/Stock.php`](file:///C:/wamp64/www/stok-takip/app/Models/Stock.php) — Stok bakiyelerinin ve hareketlerinin nasıl işlendiğini görün.
9. [`app/Models/Recipe.php`](file:///C:/wamp64/www/stok-takip/app/Models/Recipe.php) — Güneş paneli reçetelerini (BOM) ve hammadde oranlarını inceleyin.
10. [`app/Models/PurchaseRequest.php`](file:///C:/wamp64/www/stok-takip/app/Models/PurchaseRequest.php) — Satın alma talebi onay zincirini kavrayın.
11. [`app/Models/PurchaseOrder.php`](file:///C:/wamp64/www/stok-takip/app/Models/PurchaseOrder.php) — Satın alma siparişi oluşturma ve tedarikçi akışını öğrenin.
12. [`app/Models/PurchaseReceipt.php`](file:///C:/wamp64/www/stok-takip/app/Models/PurchaseReceipt.php) — Mal kabul ve irsaliye girişinin stoğu nasıl güncellediğini görün.
13. [`app/Models/Mes.php`](file:///C:/wamp64/www/stok-takip/app/Models/Mes.php) — MES iş emirleri ve istasyon olay modelini çözün.
14. [`app/Services/MesSimulationService.php`](file:///C:/wamp64/www/stok-takip/app/Services/MesSimulationService.php) — Üretim simülasyon mantığını öğrenin.
15. [`app/Services/ProductionStockService.php`](file:///C:/wamp64/www/stok-takip/app/Services/ProductionStockService.php) — Üretim tamamlandığında hammaddelerin depodan nasıl düştüğünü görün.
16. [`app/Services/OeeCalculationService.php`](file:///C:/wamp64/www/stok-takip/app/Services/OeeCalculationService.php) — OEE kullanılabilirlik, performans ve kalite formüllerini kavrayın.
17. [`app/Models/Inventory.php`](file:///C:/wamp64/www/stok-takip/app/Models/Inventory.php) — Demirbaş ve zimmet takip mekanizmasını görün.
18. [`app/Models/Employee.php`](file:///C:/wamp64/www/stok-takip/app/Models/Employee.php) — İK personel ve vardiya yönetimini anlayın.
19. [`app/Services/MaintenanceService.php`](file:///C:/wamp64/www/stok-takip/app/Services/MaintenanceService.php) — Makine arıza ve periyodik bakım motorunu inceleyin.
20. [`app/Models/EnergyDashboard.php`](file:///C:/wamp64/www/stok-takip/app/Models/EnergyDashboard.php) — Enerji tüketimi ve GES üretim hesaplarını görün.
21. [`views/layouts/sidebar.php`](file:///C:/wamp64/www/stok-takip/views/layouts/sidebar.php) — Menü hiyerarşisini ve izin kontrollerini inceleyin.
22. [`public/css/app.css`](file:///C:/wamp64/www/stok-takip/public/css/app.css) & [`views/`](file:///C:/wamp64/www/stok-takip/views) — Arayüz stillerini ve view şablonlarını inceleyin.