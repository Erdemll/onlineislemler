# Project State

Son guncelleme: 23 Eylul 2026

## 1. Projenin Amaci

Bu proje, Tepenet Guvenlik musterilerinin bireysel veya kurumsal hesapla kaydolabildigi bir online islemler uygulamasidir.

Ana is akisi:

1. Musteri bireysel veya kurumsal hesap acar.
2. E-posta OTP ile hesap sahipligi dogrulanir.
3. Cari Plus uzerinde musteriye ait cari hesap olusturulur veya mevcut hesap eslestirilir.
4. Cari Plus urunleri yerel hizmet kataloguna senkronize edilir (kategori bilgisiyle birlikte).
5. Musteri bir hizmet secer; hizmetler kategorilere ayrilir ve musteri kredi limitiyle uyumlu olanlar erisilebilir gosterilir.
6. Hizmete bagli sozlesmeyi inceler, cizim imzasi ve e-posta OTP ile kabul eder.
7. Imzali sozlesme uretilir ve sifreli olarak saklanir.
8. Cari Plus uzerinde satis faturasi olusturulup kesinlestirilir.
9. Musteri faturayi Akbank Akode (Tosla) uzerinden 3D Secure ile oder; callback dogrulanir, fatura `paid` olur ve Cari Plus'a tahsilat kaydi islenir.
10. Odeme sistemi akisi devrededir; callback hash formulu ve mutabakat dayanikliligi guncellendi. Gercek Tosla callback'i ile uc uca dogrulama bekleniyor (bolum 11).

Telefon dogrulamasi su anda zorunlu degildir. SMS hizmeti devreye alindiginda kurumsal musteriler icin `mobile_phone` alanina OTP gonderilmesi planlanmistir.

## 2. Teknoloji Yigini

- PHP: `^8.3`, calisma ortami PHP 8.5 ile uyumlu
- Laravel: 13.31
- Test: Pest 5
- CSS: Tailwind CSS 4
- Frontend build: Vite 8
- Imza: `signature_pad`
- PDF: Dompdf, FPDF ve FPDI
- E-posta: Resend/Laravel Mail
- SMS altyapisi: Verimor servisi mevcut, ancak telefon OTP zorunlu degil
- Odeme: Akbank Akode / Tosla Sanal POS (3D Secure, ortak odeme sayfasi)
- Gelistirme veritabani: SQLite 3.45
- Production veritabani: MariaDB 11.4.13
- Varsayilan session/cache/queue: database tabanli

## 3. Kimlik Dogrulama ve Yetkilendirme

Uygulamada iki ayri session guard bulunur:

- `web`: yonetici kullanicilari, `users` tablosu
- `customer`: musteriler, `customers` tablosu

Yonetici erisimi `is_admin` ve `access-admin` Gate kontrolu ile korunur.

Musteri guvenlik sinirlari:

- Session ID giristen sonra yenilenir.
- `session_version`, sifre degisikligi veya guvenlik islemleri sonrasinda eski oturumlari gecersiz kilar.
- `EnsureCustomerSessionIsCurrent`, session surumunun yaninda `is_active` durumunu da kontrol eder.
- Pasif musteri mevcut veya remember-me oturumuyla devam edemez.
- E-posta dogrulamasi kritik musteri sayfalari icin zorunludur.
- Cari Plus cari hesap eslesmesi hizmet, sozlesme ve fatura ekranlari icin zorunludur.
- Telefon dogrulama middleware'i hazirdir fakat bilerek zorunlu route gruplarina eklenmemistir.

Production icin session payload sifrelemesi zorunlu kabul edilir:

```env
SESSION_ENCRYPT=true
```

TCKN, vergi numarasi, imza verisi ve OTP gibi hassas alanlar validation hatalarinda session'a flash edilmez.

## 4. Musteri Kaydi

Desteklenen hesap turleri:

- Bireysel: TCKN zorunlu
- Kurumsal: vergi numarasi, vergi dairesi ve firma unvani zorunlu

TCKN ve vergi numaralari:

- Veritabaninda Laravel encryption ile sifreli saklanir.
- Arama ve benzersizlik kontrolu icin HMAC blind-index tutulur.
- Blind-index artik `APP_KEY` yerine bagimsiz `CUSTOMER_IDENTITY_INDEX_KEY` kullanir.
- Anahtar rotasyonunda gecici olarak onceki anahtarlar desteklenir.

Gerekli environment degerleri:

```env
CUSTOMER_IDENTITY_INDEX_KEY=
CUSTOMER_IDENTITY_INDEX_PREVIOUS_KEYS=
```

Mevcut hashleri yeniden uretmek icin:

```bash
php artisan customers:reindex-identities
```

Kayit ekranindaki tekrar kullanilan e-posta, telefon, TCKN ve vergi numarasi hatalari hesap varligini dogrudan aciklamayan genel mesajlar kullanir.

## 5. Musteri Yonetimi ve Kredi Limiti

Admin panelinde musteriler sayfasi (`admin/musteriler`) ve duzenleme sayfasi (`admin/musteriler/{customer:uuid}/duzenle`) vardir. Yan menuye "Musteriler (05)" olarak eklenmistir.

- Controller: `App\Http\Controllers\Admin\CustomerController` (index/edit/update).
- Duzenleme sayfasinda kimlik bilgileri sansurlu gosterilir: `Customer` modelindeki `masked_national_id` ve `masked_tax_number` append attribute'lari yalnizca **son 2 karakteri** acik birakir (ornek: `**********34`). Sansurleme servis tarafinda yapilir; frontend'e ham deger asla gonderilmez. E-posta ve telefon normal gosterilir.
- Kredi limiti `customers.credit_limit` sutununda **kurus** (integer) olarak saklanir; ondalik hassasiyet kaybini onlemek icin TL karsiligi goruntude `/ 100` ile, giris formunda `* 100` ile cevrilir. Degisken tipi `unsignedBigInteger`, varsayilan `0`.
- Limit atamasi yalniz admin tarafindan yapilir; musteri limitini kendisi degistiremez.
- **Kredi limiti kurali:** `limit > 0` ise urun fiyati (kurus) `<=` limit olan urunler erisilebilir; `limit = 0` (henuz atanmamis) ise hicbir urun erisilemez (musteri karari).
- `customers:backfill-uuids` komutu: bos/null `uuid` degerlerine `Str::uuid` atar. Bos uuid, `route('admin.customers.edit', ...)` cagrisinda "Missing required parameter" hatasi uretiyordu; sebep veritabaninda `uuid = ''` olan kayitlardi. Komut idempotenttir.

## 6. Cari Plus Entegrasyonu

Sinir kontrati: `App\Contracts\CariPlusGateway`

Gercek istemci: `App\Services\CariPlus\CariPlusClient`

Desteklenen islemler:

- Cari hesap olusturma
- Cari hesabi kod ile bulma
- Urun listeleme ve urun bulma
- Urun olusturma
- Satis faturasi olusturma
- Satis faturasini kesinlestirme
- Musteriye ait satis faturalarini listeleme
- Kasa/banka hesaplarini listeleme (`GET /v1/company-accounts`)
- Fatura tahsilati kaydetme (`POST /v1/invoice-collections`)

Guvenlik ve dayaniklilik kararlari:

- Tum yazma isteklerinde sabit idempotency anahtari kullanilir.
- Baglanti ve cevap timeout degerleri aciktir.
- Baglanti hatalari, `429` ve gecici `5xx` cevaplari sinirli backoff ile yeniden denenir.
- `401` cevabinda access token bir kez yenilenir ve istek tekrar edilir.
- Access token database cache icinde plaintext degil, Laravel Crypt ile sifreli tutulur.
- Cache anahtari Cari Plus URL ve client kimligine gore namespace edilir.
- Basarili HTTP cevabi tek basina yeterli sayilmaz; response semasi ve pozitif resource ID kontrol edilir.
- Uzak servisin serbest hata mesaji kullaniciya, loga veya finansal kayda dogrudan tasinmaz.
- Tahsilat (collection) islemi `type=bank_transfer` ile yapilir; cunku Akode odemesi banka hesabina girer ve bu yuzeyden yalnizca `cash`/`bank_transfer` yazilabilir. Tahsilat idempotency anahtari: `tahsilat-{payment-uuid}`.
- Cari Plus faturasinda `paid_amount` tahsilat kayitlarindan turer; kalan sifirlaninca fatura otomatik `paid` olur (`collection_status` elle isaretlenen ayri bir bayraktir, dokunulmaz).

## 7. Urun Katalogu

Musteri urun sayfasinda manuel yenileme butonu yoktur.

Sayfa davranisi:

1. Hizmetler sayfasi server-side mevcut yerel katalogla acilir.
2. JavaScript otomatik olarak CSRF korumali POST senkronizasyon istegi gonderir.
3. Senkronizasyon sirasinda yukleme animasyonu ve dusurulmus katalog opakligi gosterilir.
4. Basarili islemden sonra sayfa bir kez yeniden yuklenir.
5. Dahili yeniden yuklemede tekrar senkronizasyon yapilmaz; kullanicinin sonraki manuel yenilemesi yeni senkronizasyon baslatir.
6. Hata halinde mevcut cache katalogu ekranda kalir ve basit hata mesaji gosterilir.

Kategori ve limit gorunurlugu:

- `services.category_id` ve `services.category_name` Cari Plus urun yanitindaki `category` nesnesinden (`{id, name}`) senkronize edilir; kategorisiz urunler `null` kalir.
- Hizmetler sayfasi `category_name`'e gore gruplanir (kategorisizler "Kategorisiz" basliginda). Gruplama ve limit hesabi `App\Http\Controllers\Customer\ServiceController::index` icinde yapilir.
- `Service::grossPriceInKurus()` fiyati kurusa cevirir; `Customer.credit_limit` (kurus) ile karsilastirilir.
- Limiti asan urun kartinda "Bu hizmetin tutari mevcut limitinizi asiyor. Limitiniz yetmemektedir." uyarisi ve "Limit yetersiz" (disabled) butonu gosterilir; urun yine de listelenir.
- `limit = 0` kurali icin bolum 5'e bakin.

Koruma katmanlari:

- Musteri basina dakikada 10 otomatik senkronizasyon istegi
- Global `cari-plus.products.sync` cache lock
- Tum uzak sayfalar alinmadan yerel katalog degistirilmez
- Hatali bir kayitta DB transaction geri alinir
- Pozitif urun ID, gercek boolean, `TRY`, negatif olmayan fiyat ve `0-100` vergi orani zorunludur
- Cari Plus'ta artik bulunmayan urunler pasif hale getirilir
- Cari Plus ayni SKU'yu farkli urun ID'leri icin kullaniyorsa senkron reddedilir; mevcut bagli hizmet yanlis urune yeniden baglanmaz.

Admin urun senkronizasyon endpoint'i ayrica korunmaya devam eder.

## 8. Sozlesme Akisi

Ana modeller:

- `Contract`
- `ContractVersion`
- `ServiceOrder`
- `ContractSigningChallenge`
- `ContractAcceptance`
- `ContractAcceptanceEvent`

Akis:

1. Hizmete ait aktif ve yayinlanmis sozlesme surumu secilir.
2. Hizmet adi, urun ID, fiyat, vergi ve para birimi `service_orders` icinde snapshot olarak tutulur.
3. Musterinin PNG cizim imzasi boyut, MIME ve piksel sinirlariyla dogrulanir.
4. Imza Laravel encryption ile sifreli dosyaya yazilir.
5. Alti haneli e-posta OTP gonderilir.
6. Challenge ayni signing session belirtecine baglanir.
7. Kabul sirasinda sahiplik, OTP hash, sure, deneme sayisi, session ve challenge durumu yeniden kontrol edilir.
8. Challenge dogrulamasi, tuketimi ve kabul kaydi ayni kilitli transaction icinde tamamlanir.
9. Kabul anindaki IP, user-agent ve session hash kabul kaydina yazilir.
10. Imzali PDF olusturulur ve sifreli saklanir.
11. Yalnizca yeni kabul kaydini olusturan islem fatura akisini tetikler.

Tekrarlanan kabul istekleri yan etkisizdir; odendi, iptal edildi veya iade edildi durumundaki faturalar yeniden kesilmez.

Challenge baslatma akisi provisional challenge kullanir. Mail basarili olduktan sonra siparis satiri kilitlenir, eski challenge'lar tuketilir ve yeni challenge aktif edilir. DB veya storage hatasinda yeni imza temizlenir.

## 9. Sozlesme Belge Depolamasi

Disk: Laravel `local`

Root:

```text
storage/app/private/
```

Dosya yerlesimi:

```text
storage/app/private/contracts/
├── versions/{contract-uuid}/{version}-{version-uuid}.pdf
├── signatures/{service-order-uuid}/{challenge-uuid}.enc
└── acceptances/{service-order-uuid}/{acceptance-uuid}.pdf
```

Not: `.pdf` uzantili source ve acceptance dosyalari diskte plaintext PDF degildir. Icerik `contract-document:v1:` surum prefixi ve AES-256-CBC ciphertext olarak saklanir.

Belge sifreleme servisi:

```text
App\Services\Contracts\EncryptedContractDocumentStorage
```

Sifreleme anahtarlari:

```env
CONTRACT_DOCUMENT_KEY=
CONTRACT_DOCUMENT_PREVIOUS_KEYS=
CONTRACT_DOCUMENT_ALLOW_LEGACY_PLAINTEXT=false
```

`CONTRACT_DOCUMENT_KEY` su bicimleri kabul eder:

- Tam 32 byte raw deger
- `base64:` onekli, 32 byte'a decode olan Base64 deger
- Oneksiz, 32 byte'a decode olan Base64 deger

Mevcut plaintext belgeleri yerinde sifrelemek icin:

```bash
php artisan contracts:encrypt-documents
```

Komut idempotenttir. Bu oturumda yerel veritabanina bagli iki belge sifrelendi; ikinci calistirma `0` belge degistirdi.

Kaynak PDF yayinlanmadan once FPDI ile parse edilir. En az bir, en fazla 500 import edilebilir sayfa zorunludur. Yalnizca `%PDF-` header kontrolu yeterli kabul edilmez.

Indirme sirasinda:

- Route sahipligi veya admin yetkisi kontrol edilir.
- Ciphertext cozulur.
- Plaintext SHA-256 hash dogrulanir.
- `private, no-store` ve `nosniff` headerlariyla cevap verilir.

## 10. Faturalama

Ana modeller:

- `Invoice`
- `InvoiceItem`

Yerel durumlar:

```text
pending
draft
unpaid
paid
cancelled
partial_refund
refunded
failed
```

Kararlar:

- Fatura yalnizca sozlesme kabul edildikten sonra olusturulur.
- Hizmet/fiyat/vergi bilgileri siparis snapshot'indan kullanilir.
- Cari Plus draft ve issue islemlerinde ayri fakat sabit idempotency anahtarlari vardir.
- Issue cevabindaki ID, olusturulan uzak fatura ID'siyle ayni olmak zorundadir.
- Draft/issue cevabinda uzak durum, para birimi ve toplam, yerel sozlesme/fatura snapshot'iyla uyusmuyorsa odeme asamasina gecilmez.
- Retry yalnizca `draft` ve `failed` durumlarinda yapilir.
- Terminal durumlar yeniden issue edilmez.
- Fatura senkronizasyonu bilinmeyen status, negatif tutar, gecersiz tarih veya desteklenmeyen para biriminde tum islemi reddeder.
- `paid`, `cancelled` ve `refunded` gibi terminal durumlar eski uzak cevaplarla gerilemez.
- Cari Plus odeme bildirdiginde bagli hizmet talebi `paid` durumuna ilerler.
- Portal icinde odeme Akode (Tosla) ile alinir (bolum 11); basarili odemede yerel fatura `paid`, bagli hizmet talebi `paid` olur ve Cari Plus'a `bank_transfer` tahsilat kaydi islenir. Cari Plus tarafinda kalan sifirlaninca uzak fatura otomatik `paid` olur.

## 11. Odeme Sistemi (Akode / Tosla)

Saglayici: Akbank Akode / Tosla Sanal POS.

Ortam URL'leri:

- Test: `https://prepentegrasyon.tosla.com/api/Payment/`
- Canli: `https://entegrasyon.tosla.com/api/Payment/`

Sinir kontrati: `App\Contracts\ToslaGateway`

Gercek istemci: `App\Services\Tosla\ToslaClient`

Hash servisi: `App\Services\Tosla\ToslaHash`

Hata sinifi: `App\Exceptions\ToslaException`

### Mimari kararlar

- **Kart formu portal sunucusuna inmez.** Musteri, `threeDPayment` ile alinan `ThreeDSessionId` ile Tosla'nin **ortak odeme sayfasina** (`{ortam}/api/Payment/threeDSecure/{sessionId}`) yonlendirilir; kart bilgisi dogrudan Tosla'ya gider. Callback'te beklenmedik CardNo/CVV alanlari gelirse audit payload'ina alinmaz.
- **Hash mekanizmasi:** istek hash'i `SHA512(apiPass + clientId + apiUser + rnd + timeSpan)` -> Base64. `apiPass` yalniz sunucu tarafinda kalir; frontend'e, loga veya DB'ye asla yazilmaz. Her istekte yeni `rnd`; `timeSpan` GMT+3 (`yyyyMMddHHmmss`), maksimum 1 saat fark.
- **Callback hash dogrulamasi:** Resmi Tosla PHP paketindeki gibi `HashParameters` varsa gelen alan adlari ve sirasi esas alinir; `ClientId` ve `ApiUser` sunucu konfigurasyonundan okunur. `HashParameters` yoksa dokumandaki sabit siralama kullanilir. Eksik/tekrarli alanlar veya kimlik uyusmazligi reddedilir. Gecersiz hash icin HTTP 400 ve faturaya guvenli donus baglantisi verilir; finansal durum degismez.
- **Basari teyidi iki asamalidir:** callback'te `MdStatus=1`, `BankResponseCode=00`, `RequestStatus=1` + `inquiry` sorgusu (`RequestStatus=1`, banka kodu `00`, OrderId, tutar ve TRY/949 eslesmesi). Mutabakat komutu, callback gelmediginde de guvenilir inquiry sonucuyla odemeyi tamamlayabilir. Tutar DB'deki odeme snapshot'indan alinir.
- **Inquiry request_status haritasi:** `1` dogrulanmis basari; `0` hatali; `2/4` iptal; `10/11/12` ve bilinmeyenler henüz kesinlesmemis sayilir. Iptal/basarisiz islemler 48 saat icinde yeniden sorgulanir; eski bir siparis `--order=` ile elle sorgulanabilir.
- **Tutar/para birimi:** tutarlar kuruş (integer), para birimi `949` (TRY).
- **Odeme baslatma:** uzak Cari Plus faturasi ID'si, TRY ve pozitif tutar zorunludur. Production'da `AKODE_CALLBACK_URL` acikca ayarlanir; fatura basina atomic cache lock eszamanli 3D oturumu olusumunu engeller.
- **orderId:** 20 karakter, tahmin edilemez (`OI` + rastgele buyuk harf), benzersiz (DB unique). Fatura ile iliskisi `payments.order_id` uzerindendir.
- **Idempotency:** ayni `order_id` icin ikinci tahsilat olusturulmaz; yalnizca `unpaid` fatura icin odeme baslatilir; fatura basina atomic cache lock ve bekleyen (`pending`) odeme kontrolu vardir; `paid`/`refunded` tekrar basariliya cevrilmez, `cancelled`/`failed` yalnizca dogrulanmis inquiry sonucuyla kurtarilir. Baska bir odemeyle odenmis fatura yeniden odenmis isaretlenmez.

### Veri modeli

- `payments`: `uuid`, `customer_id`, `invoice_id`, `order_id` (unique), `three_d_session_id`, `transaction_id`, `amount_kurus`, `currency`, `status` (`pending|paid|failed|cancelled|refunded`), `installment_count`, `response_code`, `paid_at`, `cari_plus_collection_id`, `cari_plus_collection_synced_at`.
- `payment_callbacks`: boyut sinirini asmayan callback'ler kaydedilir (hash gecersiz olsa bile) — `order_id`, `payment_id`, `bank_response_code`, `request_status`, `md_status`, `hash_valid`, `received_at`, `payload` (kart numarasi/CVV ayiklanmis istek JSON). Replay tespiti ve teshis icin kullanilir.
- Enum: `App\Enums\PaymentStatus`.

### Akis

1. Fatura sayfasinda `unpaid` faturada "Ode" butonu -> `App\Http\Controllers\Customer\InvoicePaymentController::store`.
2. `App\Services\Payments\StartInvoicePayment`: sahiplik ve durum kontrolleri, `orderId` uretimi, `threeDPayment` cagrisi (tutar DB'den), `payments` kaydi (`pending`), Tosla ortak odeme sayfasina yonlendirme.
3. Musteri 3D'yi tamamlar; Tosla sonucu `POST /odeme/callback/akode` adresine gonderir (route: `payment.callback.akode`).
4. `App\Http\Controllers\Payments\ToslaCallbackController`: ham veri `payment_callbacks`'a yazilir; `HashParameters` veya eski sabit sirayla hash dogrulanir; `MdStatus`/`BankResponseCode`/`RequestStatus` kontrol edilir; `inquiry` ile OrderId, tutar, para birimi ve durum teyit edilir.
5. `App\Services\Payments\RecordPaymentSuccess`: kilitli transaction icinde `payments -> paid` (+`paid_at`), `invoices -> paid`, `service_orders -> paid`.
6. `App\Services\Payments\SyncPaymentCollectionToCariPlus`: Cari Plus `POST /v1/invoice-collections` (`type=bank_transfer`, `amount=odeme snapshot'i`, `company_account_id=config`, idempotency `tahsilat-{payment-uuid}`). Tahsilat tarihi ilk odeme zamanindan sabitlenir; ayni key ile retry govdesi degismez. Donen fatura/tutar/para birimi/tur/hesap esitligi zorunludur. Eksik hesap veya uzak fatura ID'si senkronu basarili isaretlemez; ayar tamamlaninca `payments:reconcile` tekrar dener.
7. Cevap: musteriyi fatura sayfasina yonlendiren kucuk bir HTML sayfa (Tosla tarayici akisina cevap olarak 200 doner).

### Guvenlik notlari

- `bootstrap/app.php` icinde `validateCsrfTokens(except: ['odeme/callback/akode'])` ile callback route'u CSRF disindadir. **Laravel 13'te CSRF middleware'i `PreventRequestForgery` olarak yeniden adlandirilmistir**; eski `ValidateCsrfToken` adiyla route uzerinde `withoutMiddleware` calismaz (419 hatasi uretir). Bu hataya dusuldu ve `validateCsrfTokens(except)` ile cozuldu.
- Callback route'u halka aciktir (auth yok); finansal islem hash + inquiry dogrulamasina dayanir. 16 KB istek boyutu ve IP basina dakikada 300 istek siniri vardir.
- `ToslaClient` baglanti hatasi, `429` ve gecici `5xx` icin sinirli retry kullanir; `apiPass` hicbir yerde aciga cikmaz.

### Environment

```env
AKODE_BASE_URL=https://prepentegrasyon.tosla.com/api/Payment/
AKODE_CALLBACK_URL=https://uygulama-alan-adiniz/odeme/callback/akode
AKODE_CLIENT_ID=
AKODE_API_USER=
AKODE_API_PASS=
AKODE_CONNECT_TIMEOUT=3
AKODE_TIMEOUT=15
```

Cari Plus tahsilat hedef hesabi:

```env
CARI_PLUS_COLLECTION_ACCOUNT_ID=
```

`AKODE_CALLBACK_URL` production icin zorunlu, internetten erisilebilir HTTPS donus adresidir; lokal ortamda tanimsizsa aktif istegin route URL'si kullanilir. `CARI_PLUS_COLLECTION_ACCOUNT_ID` Cari Plus `GET /v1/company-accounts` listesinden alinacak **aktif TRY banka** hesap id'sidir; bos ise yerel odeme `paid` kalir fakat tahsilat senkronu tekrar denenecek sekilde acik kalir.

### Gercek ortamdan kalan dogrulama

- Gercek callback hash dogrulamasi basarisiz oldu (`hash_valid=false` -> HTTP 400; banka islemi basarili olmustu). Gercek callback'te `OrderId`, `MdStatus=1`, `BankResponseCode=00`, `RequestStatus=1` dogru geldi; ancak `Code`, `Message`, `TransactionId`, `BankResponseMessage` alanlari gelmedi (null kaydedildi) — Tosla'nin gonderdigi alan adlari/degerleri dokumandakiyle birebir uyusmuyor.
- Resmi Tosla PHP paketi `HashParameters` sirasini kullaniyor; kod bu sirayi destekleyecek sekilde duzeltildi. Eski callback kaydinda ham `payload` bos oldugundan bu olay icin kesin hash sebebi geriye donuk olarak kanitlanamiyor; yeni gercek denemede ham callback ve hash sonucu kontrol edilmeli.
- Test ortami davranisi: ayni islem icin `inquiry` `request_status=2` (iptal edildi) dondu; odeme `cancelled` isaretlendi. Sonradan basarili sorgu gelirse 48 saat icinde otomatik, daha eskiyse `payments:reconcile --order=<order-id>` ile kontrollu olarak tamamlanir. Para hareketi tek basina odeme basarisi sayilmaz.
- Test ortami dogrulamasi: `php artisan payments:reconcile` komutu gercek inquiry ile calistigi dogrulandi.

## 12. Audit Butunlugu

Audit tablosu: `contract_acceptance_events`

Yeni olaylar:

- `hmac-sha256-v2` ile imzalanir.
- Onceki event hashini icerir.
- Bagimsiz `CONTRACT_AUDIT_HMAC_KEY` kullanir.

Eski olaylar:

- `sha256-v1` olarak isaretlenir.
- Eski timestamp serilestirme kaybi nedeniyle yalnizca yapisal zincir kontrolunden gecer.
- Migration sonrasinda DB triggerlariyla degistirilemez hale gelir.

MariaDB ve SQLite icin `contract_acceptances` ve `contract_acceptance_events` tablolarinda `UPDATE` ve `DELETE` islemlerini reddeden triggerlar vardir.

Audit kontrolu:

```bash
php artisan contracts:audit-verify
php artisan contracts:audit-verify --order=<service-order-uuid>
```

## 13. Veritabani Yapisi

Yerel gelistirme veritabani `database/database.sqlite` dosyasidir. Su anda 25 tablo vardir ve 34 migration'in tamami uygulanmistir.

Ana tablolar:

| Tablo | Amac |
| --- | --- |
| `users` | Yonetici hesaplari |
| `customers` | Bireysel/kurumsal musteriler, Cari Plus eslesmesi ve kredi limiti (kurus) |
| `otp_verifications` | E-posta, telefon, parola sifirlama OTP kayitlari |
| `services` | Cari Plus urunlerinden olusan yerel katalog (kategori bilgisiyle) |
| `contracts` | Sozlesme ana kayitlari |
| `contract_versions` | Yayinlanmis, hashli sozlesme surumleri |
| `contract_service` | Sozlesme surumu-hizmet pivotu |
| `service_orders` | Musteri hizmet talepleri ve finansal snapshot |
| `contract_signing_challenges` | OTP, imza ve session bagli kabul challenge'lari |
| `contract_acceptances` | Degistirilemez sozlesme kabul kaniti |
| `contract_acceptance_events` | HMAC hash zincirli audit olaylari |
| `invoices` | Yerel/Cari Plus fatura kayitlari |
| `invoice_items` | Fatura kalemleri |
| `payments` | Akode odeme kayitlari (kurus tutar, order_id, durum, Cari Plus tahsilat senkronu) |
| `payment_callbacks` | Gelen callback kayitlari (hash durumu + ham payload) |
| `support_tickets` | Destek talepleri |
| `support_messages` | Musteri/admin destek yazismalari |
| `sessions` | Sifreli session payloadlari |
| `cache`, `cache_locks` | Cache ve dagitik lock kayitlari |
| `jobs`, `job_batches`, `failed_jobs` | Queue altyapisi |

Production MariaDB 11.4 migration kararlari:

- `customers.cari_plus_current_account_id` benzersizdir; bir uzak cari hesap iki musteriye baglanamaz.
- Challenge, kabul, audit event, siparis ve fatura sahipligi composite foreign keylerle korunur.
- Finansal degerler negatif olamaz; vergi `0-100` araligindadir.
- Enum/status alanlari MariaDB `CHECK` constraintleriyle sinirlanir (`payments.status` ve `amount_kurus >= 0` dahil).
- Finansal, hukuki ve destek kaniti tablolari cascade ile fiziksel olarak silinmez; `RESTRICT` kullanilir.
- Migration once mevcut duplicate ve iliskisel tutarsizliklari kontrol eder; sorun varsa guvenli bicimde durur.
- MariaDB DDL tablo kopyalama/metadata lock uretebilecegi icin production'da bakim penceresinde calistirilmalidir.

## 14. Onemli Artisan Komutlari

```bash
# Guvenli yonetici olustur/guncelle
php artisan admin:create admin@example.com

# Yalnizca local/test ortaminda dogrulanmis test musterisi
php artisan customer:create-test

# Kimlik blind-indexlerini yeni anahtarla yenile
php artisan customers:reindex-identities

# Bos/null uuid'li musterilere UUID ata (route binding icin)
php artisan customers:backfill-uuids

# Eski sozlesme belgelerini yerinde sifrele
php artisan contracts:encrypt-documents

# Audit zincirlerini dogrula
php artisan contracts:audit-verify

# Bekleyen odemeleri Akode inquiry ile kapatir, eksik Cari Plus tahsilat senkronlarini tamamlar
php artisan payments:reconcile

# Migration
php artisan migrate --force

# Test
php artisan test --compact

# Frontend
npm run build
```

Console parola kararlari:

- Parola command-line option olarak alinmaz.
- Gizli interaktif prompt kullanilir.
- Kullanici tarafindan girilen parola terminale yazilmaz.
- Sadece komut tarafindan uretilen parola bir kez gosterilir.
- Test musterisi komutu production ortaminda calismaz.

`payments:reconcile` komutu `routes/console.php` icinden 30 dakikada bir cakisma onlemeyle planlanir. Calisan scheduler gerekli; lokal ortamda scheduler calismiyorsa komut elle calistirilabilir. Eski/tekil siparis icin `php artisan payments:reconcile --order=<order-id>` kullanilabilir; yeni kart tahsilati baslatmaz.

## 15. Production Environment Gereksinimleri

Secret degerleri bu dosyaya veya Git'e yazilmamalidir.

Gerekli degiskenler:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://uygulama-alan-adiniz
APP_KEY=
APP_PREVIOUS_KEYS=
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

CONTRACT_AUDIT_HMAC_KEY=

CUSTOMER_IDENTITY_INDEX_KEY=
CUSTOMER_IDENTITY_INDEX_PREVIOUS_KEYS=

CONTRACT_DOCUMENT_KEY=
CONTRACT_DOCUMENT_PREVIOUS_KEYS=
CONTRACT_DOCUMENT_ALLOW_LEGACY_PLAINTEXT=false

CARI_PLUS_BASE_URL=
CARI_PLUS_CLIENT_ID=
CARI_PLUS_CLIENT_SECRET=
CARI_PLUS_CONNECT_TIMEOUT=3
CARI_PLUS_TIMEOUT=10
CARI_PLUS_COLLECTION_ACCOUNT_ID=

AKODE_BASE_URL=https://entegrasyon.tosla.com/api/Payment/
AKODE_CALLBACK_URL=https://uygulama-alan-adiniz/odeme/callback/akode
AKODE_CLIENT_ID=
AKODE_API_USER=
AKODE_API_PASS=
AKODE_CONNECT_TIMEOUT=3
AKODE_TIMEOUT=15
```

Onerilen gecis sirasi:

1. Veritabani ve `storage/app/private` yedegini al.
2. Uygulamayi bakim moduna al.
3. Yeni secret degerlerini tanimla.
4. Gerekirse belge gecisi icin gecici olarak `CONTRACT_DOCUMENT_ALLOW_LEGACY_PLAINTEXT=true` ayarla.
5. `php artisan config:clear` calistir.
6. `php artisan migrate --force` calistir.
7. `php artisan customers:reindex-identities` calistir.
8. `php artisan contracts:encrypt-documents` calistir.
9. `php artisan contracts:audit-verify` calistir.
10. Legacy belge ve identity previous-key bayraklarini kapat.
11. `php artisan config:cache` calistir.
12. Uygulamayi bakim modundan cikar.

Production web sunucusunun document root'u `public/` olmali; `.env`, veritabani, `storage/app/private` ve sozlesme belgeleri web'den erisilemez olmalidir. SSL sertifikasi dogru, `APP_URL` ile `AKODE_CALLBACK_URL` ayni dis alan adina isaret ediyor olmali. `MAIL_MAILER=log` OTP teslim etmez: production mail servisi, `MAIL_FROM_ADDRESS` ve kuyruk calisiyorsa worker gerekir. Session/cache/scheduler tekden cok sunucuda ortak ve atomic lock destekli bir store kullanmali; `schedule:run` dakikada bir gercekten tetiklenmelidir. Eski sifreli veriler icin anahtarlar (APP_KEY, kimlik/audit/belge anahtarlari) yedeklenmeli, sessizce yenilenmemelidir. Ortamda `CARI_PLUS_COLLECTION_ACCOUNT_ID` icin `collections:write` yetkisi ve aktif TRY banka hesabi; cari/urun/fatura istekleri icin ilgili read/write scope'lari ve `invoices.invoice.issue` panel izni dogrulanmalidir. Test ve canli Akode/Cari Plus kimlik bilgileri ayri tutulmalidir. Bakim modu aktifken 3D callback'ler engellenebileceginden acik odemeler tamamlanmadan bakim moduna gecilmemelidir.

Frontend icin yayin sunucusunda `npm run build` yapin veya guncel `public/build` ciktisini aktarın; `public/hot` (lokal Vite isaretcisi) yayin paketinde bulunmamalidir. Depodaki `public/build.zip` eski statik artefakttir, yeni build yerine kullanilmamalidir. `public/sozlesmeler/` altindaki ornek bos sozlesme kamuya aciktir; imzali/kisisel veri tasiyan belgeler sadece sifreli ozel depolamada tutulur. Ilk canli Akode denemesinden once eski bekleyen islemleri bankanin nihai durumuyla inceleyin; islem acikken bakim modu veya eski/yeni anahtar karisikligi callback'i bozabilir.

`SESSION_ENCRYPT=true` gecisi mevcut sessionlari gecersiz kilabilir. Kullanicilarin yeniden giris yapmasi beklenen davranistir.

Canli Akode gecisinde `AKODE_BASE_URL` canli adrese degistirilmeli; test ortaminda yapilan islemler canliya aktarilmaz.

## 16. Test ve Dogrulama Durumu

Son tam dogrulama:

- Pest: 226 test basarili
- Assertion: 858
- Laravel Pint: basarili
- Vite production build: basarili (bu oturumda)
- Composer audit: bilinen acik yok (bu oturumda)
- NPM audit: bilinen acik yok (bu oturumda)
- Contract document encryption: ilk calisma 2 belge, ikinci calisma 0 belge (onceki oturumda)

Bu oturumda odeme, Cari Plus ve yayin oncesi guvenlik duzeltmeleri sonrasi tam test paketi calistirildi; route cache, Blade derleme ve frontend build dogrulandi. Gercek banka callback'i ile canli dogrulama henuz yapilmadi.

Testlerde dis Cari Plus, Tosla (Akode) ve mail/SMS istekleri fake edilir (`Tests\Fakes\FakeCariPlusGateway`, `Tests\Fakes\FakeToslaGateway`).

Odeme testleri:

- `tests/Unit/Payments/ToslaHashTest` — istek ve callback hash formullerinin dogrulugu.
- `tests/Feature/Customer/InvoicePaymentTest` — odeme baslatma, yonlendirme, cift baslatma engeli, durum/ sahiplik kontrolleri.
- `tests/Feature/Payments/ToslaCallbackTest` — gecersiz hash reddi, basarisiz 3D, basarili odeme + Cari Plus tahsilat kaydi, idempotent tekrar, tutar uyusmazligi.
- `tests/Feature/Console/ReconcilePaymentsTest` — iptal/basarisiz mutabakati, eski siparisin tekil sorgusu, yanlis sorgu bilgisi reddi ve Cari Plus tahsilat retry.
- `tests/Feature/Services/Tosla/ToslaClientTest` — inquiry yanitinda siparis/tutar/para birimi yapisi.

## 17. Bilinen Durumlar ve Sonraki Isler

- **Akode callback gercek ortam dogrulamasi bekliyor:** `HashParameters` destegi uygulandi ve test edildi, ancak yeni bir gercek callback ile dogrulanmadi. Ham `payment_callbacks.payload` ve hash sonucu kontrol edilmeli; eski basarisiz islem icin yeni kart odemesi baslatmadan `--order=` mutabakati ve bankanin nihai durumu incelenmeli.
- Cari Plus tahsilat banka hesabi ID'sinin dogru hesaba isaret ettigi production ortaminda dogrulanmali; eksik/hatali ID ile gonderilemeyen tahsilatlar `payments:reconcile` ile tekrar denenir.
- Test ortami Akode islemleri `inquiry`'de `request_status=2` (iptal) donebildiginden tam odeme akisi dogrulamasi icin canli ortam bilgileriyle veya farkli test kartlariyla denenmelidir.
- Odeme sistemi yeni devreye alindigindan `payments` ve `payment_callbacks` tablolari icin production MariaDB constraint'leri bolum 13'teki gibi gozden gecirilmelidir.
- Telefon OTP zorunlu degil; e-posta OTP aktif yontemdir.
- SMS hizmeti alindiginda `mobile_phone` tabanli dogrulama devreye alinacak.
- Production MariaDB migration'i staging kopyasi ve production boyutuna yakin veriyle prova edilmelidir.
- Audit zincirinin son hashini harici WORM veya timestamp servisine sabitleme henuz uygulanmadi.
- Dosya izinleri production servis kullanicisina gore `0600` dosya ve `0700` dizin hedefiyle ayarlanmalidir.
- Public kayit endpoint'inde hesap numaralandirma riski genel hata mesajlariyla azaltildi; tamamen ayni zamanli ve ayni cevapli kayit akisi uygulanmadi.
- Calisma agacinda bu oturumda yapilan degisiklikler henuz commit edilmemistir.

## 18. Mimari Ilkeler

- HTTP controllerlari koordinasyon yapar; is kurallari servislerde tutulur.
- Cari Plus ve Akode gibi dis sistemler contract arkasindan kullanilir (degistirilebilirlik ve test edilebilirlik icin).
- Finansal ve hukuki islemler idempotent tasarlanir (sabit idempotency anahtarlari, tekrar isteklerde yan etkisizlik).
- Kullanici girdisi validation, yetkilendirme ve sahiplik kontrolunden ayri ayri gecer.
- Uzak servis cevabi HTTP statusuna bakilarak guvenilir sayilmaz; sema dogrulanir.
- Finansal ve hukuki snapshotlar sonradan degisen katalog/musteri verisinden bagimsiz tutulur.
- Kritik kayitlar uygulama ve veritabani seviyesinde degistirilemez hale getirilir.
- Hassas veriler uygulama seviyesinde sifrelenir; arama gereken alanlarda bagimsiz anahtarli blind-index kullanilir.
- Testler mutlu yolun yaninda yetkisiz erisim, bozuk uzak cevap, tekrar istek, race ve kismi hata senaryolarini kapsar.
- Odeme guvenligi: `apiPass` asla istemciye/loga/DB'ye dusmez; callback hash dogrulamasi fail-closed; odeme onayi dogrulanmis callback ve inquiry veya mutabakat komutunda dogrudan guvenilir inquiry teyidiyle verilir; kart verisi sunucuya inmez (ortak odeme sayfasi); tutarlar her zaman kurus (integer) snapshot'indan alinir.
