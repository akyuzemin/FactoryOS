# STOK-TAKIP — GELİŞTİRME NOTLARI VE YOL HARİTASI

## Projenin amacı

Schmid Pekintaş özelinde; stok, üretim, MES, BOM, enerji, kalite ve ileride sevkiyat süreçlerini tek sistemde birleştiren web tabanlı fabrika yönetim sistemi.

## Şu ana kadar yapılanlar

1. **PHP** — Backend geliştirme dili.
2. **MVC** — Model, Controller ve View katmanlarının ayrılması.
3. **MySQL** — Malzeme, stok, üretim, MES ve enerji verilerinin tutulduğu ilişkisel veritabanı.
4. **PDO + SQL** — PHP'nin MySQL ile güvenli haberleşmesi ve sorgular.
5. **REST API + JSON** — Gerçek MES'ten üretim event'i alabilecek API: `POST /api/mes/events`.
6. **MES** — İş emirleri ve üretim olayları için Mini MES yapısı.
7. **BOM** — Üretim gerçekleşince reçeteye göre hammaddelerin otomatik tüketilmesi.
8. **Event tabanlı yapı** — `PANEL_COMPLETED` gibi üretim olaylarının alınması ve işlenmesi.
9. **MES Simulator** — Gerçek MES'e erişim yokken gerçek sisteme benzer üretim simülasyonu.
10. **Backend Worker** — Üretimin tarayıcıdan bağımsız arka planda yürütülmesi.
11. **AJAX / Polling** — Stokların sayfa yenilenmeden canlı güncellenmesi.
12. **Transaction / Rollback** — Hata veya yetersiz stokta işlemlerin tamamen geri alınması.
13. **Idempotency** — Aynı MES event'inin ikinci kez işlenerek stok düşmesini engelleme.
14. **Concurrency / Database Lock** — Aynı üretimin eşzamanlı iki kez işlenmesini engelleme.
15. **RBAC** — Kullanıcıları rollerine göre yetkilendirme.
16. **Service Layer** — MES, stok ve üretim iş mantıklarının ayrı servislerde tutulması.
17. **Traceability / Seri Takip** — Üretilen panelleri üretimden sevkiyata kadar seri numarasıyla izleme hedefi.

# GELİŞTİRME YOL HARİTASI

Amaç mevcut çalışan sistemi bozmadan, her aşamada geliştirip test etmek.

## AŞAMA 1 — Stok Sistemini Güçlendir

- [X] Malzeme ekranını geliştir.
- [X] Malzeme yanında mevcut miktarı belirgin göster.
- [X] Malzemeye tıklayınca hızlı stok giriş/çıkış işlemlerine eriş.
- [X] Kritik stok seviyelerini görünür yap.
- [X] Malzeme bazında stok hareket geçmişini geliştir.
- [X] Depo/lokasyon bazlı stok görünümünü güçlendir.

## AŞAMA 2 — MES'i Gerçek Fabrika Senaryosuna Yaklaştır

- [X] İş emri akışını geliştir.
- [X] Üretim hedefi + panel başına süre yönetimini geliştir.
- [X] MES event doğrulamasını güçlendir.
- [X] Event loglarını kullanıcıya anlaşılır göster.
- [X] Worker hata ve yeniden deneme mekanizması ekle.
- [X] Üretim hattı durumlarını takip et.

## AŞAMA 3 — MES → BOM → STOK Akışını Geliştir

- [X] BOM tüketimlerini ayrıntılı göster.
- [X] Her üretimde hangi hammaddeden ne kadar düşüldüğünü göster.
- [X] Yetersiz stokta üretimi durduran malzemeyi açıkça göster.
- [X] Transaction ve rollback testlerini artır.
- [X] Üretim ile stok hareketleri arasında güçlü referans bağlantısı oluştur.
- [X] Üretim maliyeti hesaplamasını ekle.

## AŞAMA 4 — Panel Seri Takip / Traceability

- [X] Her panele benzersiz seri numarası üret.
- [X] Seri numarasıyla panel arama.
- [X] Panelin iş emri, hat ve üretim zamanını göster.
- [X] Panelin hangi hammaddelerle üretildiğini izleyebil.
- [X] Panelin depo/lokasyonunu takip et.
- [X] Panel geçmişini tek ekranda göster.

## AŞAMA 5 — Kalite Kontrol

- [X] Üretilen paneli kalite kontrol bekleyen durumuna al.
- [X] Kalite kontrol formu oluştur.
- [X] Test sonuçlarını kaydet.
- [X] ONAYLANDI / REDDEDİLDİ / BEKLİYOR durumları ekle.
- [X] Red nedenlerini kaydet.
- [X] Kalite sonucunu seri numarasına bağla.

## AŞAMA 6 — Depo ve Sevkiyat

- [X] Mamul deposunu geliştir.
- [X] Panel raf/lokasyonunu takip et.
- [X] Sevkiyat emri oluştur.
- [X] Sevk edilecek panelleri seri numarasıyla seç.
- [X] Sevkiyat sonrası panel durumunu güncelle.
- [X] Sevk edilen panel geçmişini koru.

## AŞAMA 7 — QR Kod / Barkod

- [X] Panel seri numarası için QR kod oluştur.
- [X] QR okutunca panel detayını aç.
- [X] Depo personeline hızlı kontrol ekranı sağla.
- [X] QR üzerinden kalite ve sevkiyat durumunu göster.

## AŞAMA 8 — Enerji Yönetimini Geliştir

- [X] Enerji verilerini üretimle ilişkilendir.
- [X] Panel başına enerji tüketimi hesapla.
- [X] Hat bazında enerji tüketimi göster.
- [X] Puant saat analizini geliştir.
- [X] Enerji maliyetini üretim maliyetiyle ilişkilendir.
- [X] Alarm ve raporları geliştir.

## AŞAMA 9 — Dashboard / Raporlama

- [X] Yönetici dashboard'u oluştur.
- [X] Günlük/haftalık/aylık üretim raporları.
- [X] Stok tüketim raporları.
- [X] Üretim verimliliği.
- [X] Hammadde tüketimi.
- [X] Kalite oranı.
- [X] Sevkiyat miktarı.
- [X] Enerji tüketimi.
- [X] KPI göstergeleri.

## AŞAMA 10 — Güvenlik ve Kurumsal Yapı

- [ ] RBAC izinlerini ayrıntılandır.
- [ ] İşlem ve kullanıcı audit loglarını geliştir.
- [ ] API authentication ekle.
- [ ] MES API için güvenli erişim mekanizması oluştur.
- [ ] Hatalı/şüpheli API isteklerini logla.

## AŞAMA 11 — Gerçek MES'e Geçiş

- [ ] Gerçek MES veri formatını analiz et.
- [ ] Veri mapping'i oluştur.
- [ ] Test ortamında bağlantı kur.
- [ ] Gerçek `PANEL_COMPLETED` event'ini al.
- [ ] Gerçek üretim hattı bilgilerini eşleştir.
- [ ] Pilot üretimde doğrula.
- [ ] Gerçek sisteme geç.

# TEKNİK MİMARİ

Kullanıcı → Web UI → Controller → Service Layer → Model → MySQL

MES → REST API → Event Ingestion → Production Integration → BOM → Stock

Otomatik üretim → Backend Worker → MES Simulation / gerçek MES → Production Event → BOM → Stok OUT + Mamul IN

Canlı ekran → AJAX / Polling → API → MySQL

# ÇALIŞMA KURALI

1. Önce mevcut kodu ve veritabanını incele.
2. Çalışan özellikleri bozma.
3. Küçük ve kontrollü değişiklik yap.
4. PHP syntax kontrolü yap.
5. Veritabanı işlemlerini test et.
6. İlgili senaryoyu uçtan uca test et.
7. Ana rotalarda regresyon testi yap.
8. Sonucu kısa raporla kaydet.
9. Mevcut aşama stabil olmadan sonraki aşamaya geçme.

## Öncelik sırası

STOK → MES → BOM → SERİ TAKİP → KALİTE → DEPO → SEVKİYAT → QR → ENERJİ → RAPORLAMA → GERÇEK MES

## Durum

Temel sistem çalışıyor. Hedef; sistemi daha gerçekçi, izlenebilir, güvenli ve fabrika kullanımına yakın hale getirmek.
