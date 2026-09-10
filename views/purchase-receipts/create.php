<?php
declare(strict_types=1);

$pageTitle = 'Mal Kabul / Teslim Al: ' . ($order['order_no'] ?? '');
$activePage = 'purchase_orders';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$formatDate = static function (?string $date): string {
    if (empty($date)) return '-';
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : $date;
};

$formatNumber = static function (float|int|string $num, int $decimals = 2): string {
    return number_format((float)$num, $decimals, ',', '.');
};

$selectedItem = $pendingItems[0] ?? null;
?>

<main class="main-content">

    <!-- FLASH BİLDİRİM MESAJLARI -->
    <?php if (!empty($_SESSION['error'])): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span>⚠️</span> <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <button type="button" onclick="this.parentElement.remove();" style="background: none; border: none; font-size: 16px; cursor: pointer; color: #991b1b;">&times;</button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- 1. ÜST BAŞLIK VE GERİ DÖNÜŞ -->
    <div style="margin-bottom: 20px;">
        <div style="margin-bottom: 6px;">
            <a href="/stok-takip/public/purchase-orders/show?id=<?= (int)$order['id'] ?>" style="font-size: 12.5px; font-weight: 700; color: #3b82f6; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                &larr; <?= htmlspecialchars($order['order_no']) ?> Detayına Dön
            </a>
        </div>
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
            <div>
                <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.02em;">
                    📦 Mal Kabul &amp; Stok Girişi
                </h1>
                <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">
                    Tedarikçiden teslim alınan ürünleri doğrulayıp depo stoğuna işleyin.
                </p>
            </div>
            <div>
                <span style="font-family: monospace; font-size: 14px; font-weight: 800; background: #eff6ff; color: #1d4ed8; padding: 6px 14px; border-radius: 8px; border: 1px solid #bfdbfe;">
                    Sipariş: <?= htmlspecialchars($order['order_no']) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- 2. SİPARİŞ ÖZET BİLGİ KARTLARI -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 24px;">
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 16px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Tedarikçi Firma</div>
            <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                <?= htmlspecialchars($order['supplier_name'] ?? '-') ?>
            </div>
        </div>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 16px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Sipariş Tarihi</div>
            <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                <?= $formatDate($order['order_date']) ?>
            </div>
        </div>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 16px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Sipariş Durumu</div>
            <div style="font-size: 14px; font-weight: 800; color: #2563eb; margin-top: 2px;">
                <?= htmlspecialchars(PurchaseOrder::statusLabel($order['status'])) ?>
            </div>
        </div>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 16px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Para Birimi</div>
            <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                <?= htmlspecialchars($order['currency'] ?? 'TRY') ?>
            </div>
        </div>
    </div>

    <!-- 3. MAL KABUL GİRİŞ FORMU -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 30px;">
        <form method="POST" action="/stok-takip/public/purchase-receipts/create" onsubmit="return validateReceiptForm();">
            <?= CsrfService::tokenField() ?>
            <input type="hidden" name="purchase_order_id" value="<?= (int)$order['id'] ?>">

            <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 8px;">
                <span>📋</span> 1. Teslim Alınacak Malzeme Kalemi
            </div>

            <!-- MALZEME VE MİKTAR BİLGİLERİ -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;">
                
                <!-- Kalem Seçimi -->
                <div>
                    <label for="purchase_order_item_id" style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Sipariş Kalemi <span style="color: #dc2626;">*</span>
                    </label>
                    <select name="purchase_order_item_id" id="purchase_order_item_id" onchange="onItemChange(this.value);" style="width: 100%; padding: 10px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-weight: 600; background: #fff;">
                        <?php foreach ($pendingItems as $pi): ?>
                            <option value="<?= (int)$pi['id'] ?>" 
                                    data-material-code="<?= htmlspecialchars($pi['material_code'] ?? '') ?>"
                                    data-material-name="<?= htmlspecialchars($pi['material_name'] ?? '') ?>"
                                    data-ordered="<?= (float)$pi['ordered_quantity'] ?>"
                                    data-received="<?= (float)$pi['received_quantity'] ?>"
                                    data-remaining="<?= (float)$pi['remaining_quantity'] ?>"
                                    data-unit="<?= htmlspecialchars($pi['unit_symbol'] ?? ($pi['unit_name'] ?? 'Adet')) ?>"
                                    data-price="<?= (float)$pi['unit_price'] ?>"
                                    data-currency="<?= htmlspecialchars($pi['currency'] ?? 'TRY') ?>">
                                <?= htmlspecialchars($pi['material_code'] . ' - ' . $pi['material_name']) ?> (Kalan: <?= $formatNumber($pi['remaining_quantity']) ?> <?= htmlspecialchars($pi['unit_symbol'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Miktar Özetleri (Readonly Grid) -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px;">
                    <div>
                        <div style="font-size: 10.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">Sipariş</div>
                        <div id="disp_ordered" style="font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                            <?= $formatNumber($selectedItem['ordered_quantity'] ?? 0) ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 10.5px; font-weight: 700; color: #64748b; text-transform: uppercase;">Önceki Teslim</div>
                        <div id="disp_received" style="font-size: 14px; font-weight: 800; color: #64748b; margin-top: 2px;">
                            <?= $formatNumber($selectedItem['received_quantity'] ?? 0) ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 10.5px; font-weight: 700; color: #2563eb; text-transform: uppercase;">Kalan Teslimat</div>
                        <div id="disp_remaining" style="font-size: 15px; font-weight: 900; color: #2563eb; margin-top: 2px;">
                            <?= $formatNumber($selectedItem['remaining_quantity'] ?? 0) ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- TESLİM ALINAN MİKTAR -->
            <div style="margin-bottom: 24px; max-width: 320px;">
                <label for="received_quantity" style="display: block; font-size: 12.5px; font-weight: 700; color: #0f172a; margin-bottom: 6px;">
                    Bu Teslimatta Gelen Miktar (<span id="disp_unit"><?= htmlspecialchars($selectedItem['unit_symbol'] ?? 'Adet') ?></span>) <span style="color: #dc2626;">*</span>
                </label>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <input type="number" 
                           name="received_quantity" 
                           id="received_quantity" 
                           step="0.001" 
                           min="0.001" 
                           max="<?= (float)($selectedItem['remaining_quantity'] ?? 1000) ?>" 
                           value="<?= (float)($selectedItem['remaining_quantity'] ?? 1000) ?>" 
                           required 
                           style="width: 100%; padding: 10px 14px; font-size: 15px; font-weight: 800; border: 2px solid #3b82f6; border-radius: 8px; font-family: monospace; color: #0f172a; background: #ffffff;">
                </div>
                <small style="color: #64748b; font-size: 11.5px; margin-top: 4px; display: block;">
                    Girilen miktar kalan miktardan fazla olamaz.
                </small>
            </div>

            <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 8px;">
                <span>🏬</span> 2. Depolama &amp; Belge Bilgileri
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 20px;">
                
                <!-- Depo Seçimi -->
                <div>
                    <label for="warehouse_id" style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Giriş Yapılacak Depo <span style="color: #dc2626;">*</span>
                    </label>
                    <select name="warehouse_id" id="warehouse_id" onchange="onWarehouseChange(this.value);" required style="width: 100%; padding: 10px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-weight: 600; background: #fff;">
                        <option value="">-- Depo Seçiniz --</option>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?= (int)$wh['id'] ?>" <?= ((int)$wh['id'] === 1) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wh['code'] . ' - ' . $wh['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Lokasyon Seçimi (Dependent) -->
                <div>
                    <label for="location_id" style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Raf / Lokasyon <span style="color: #dc2626;">*</span>
                    </label>
                    <select name="location_id" id="location_id" required style="width: 100%; padding: 10px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-weight: 600; background: #fff;">
                        <option value="">-- Önce Depo Seçiniz --</option>
                    </select>
                </div>

                <!-- Teslim Tarihi -->
                <div>
                    <label for="receipt_date" style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Teslim / Kabul Tarihi <span style="color: #dc2626;">*</span>
                    </label>
                    <input type="date" name="receipt_date" id="receipt_date" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 10px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; box-sizing: border-box;">
                </div>

            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 20px;">
                
                <!-- İrsaliye No -->
                <div>
                    <label for="delivery_note_no" style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        İrsaliye Numarası
                    </label>
                    <input type="text" name="delivery_note_no" id="delivery_note_no" placeholder="Örn: IRS-2026-98765" style="width: 100%; padding: 10px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; box-sizing: border-box;">
                </div>

                <!-- Tedarikçi Belge No -->
                <div>
                    <label for="supplier_document_no" style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                        Tedarikçi Fatura / Belge No
                    </label>
                    <input type="text" name="supplier_document_no" id="supplier_document_no" placeholder="Örn: FAT-2026-5544" style="width: 100%; padding: 10px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; box-sizing: border-box;">
                </div>

            </div>

            <!-- Notlar -->
            <div style="margin-bottom: 24px;">
                <label for="notes" style="display: block; font-size: 12.5px; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    Mal Kabul Notları / Açıklama (İsteğe Bağlı)
                </label>
                <textarea name="notes" id="notes" rows="2" placeholder="Fiziksel paket durumu, teslimat koşulları veya varsa parti no..." style="width: 100%; padding: 10px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; box-sizing: border-box; resize: vertical;"></textarea>
            </div>

            <!-- AKSİYON BUTONLARI -->
            <div style="display: flex; justify-content: flex-end; gap: 12px; align-items: center; padding-top: 16px; border-top: 1px solid #f1f5f9;">
                <a href="/stok-takip/public/purchase-orders/show?id=<?= (int)$order['id'] ?>" class="button button-secondary" style="padding: 10px 18px; font-size: 13px; font-weight: 700; text-decoration: none; color: #475569; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px;">
                    Vazgeç
                </a>
                <button type="submit" class="button button-primary" style="padding: 10px 24px; font-size: 13.5px; font-weight: 800; cursor: pointer; background: #16a34a; color: #fff; border: 1px solid #15803d; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px;">
                    <span>✓</span> Mal Kabulü Tamamla ve Stoğa Giriş Yap
                </button>
            </div>

        </form>
    </div>

</main>

<script>
const warehousesData = <?= json_encode($warehouses, JSON_UNESCAPED_UNICODE) ?>;

function onWarehouseChange(whId) {
    const locSelect = document.getElementById('location_id');
    locSelect.innerHTML = '<option value="">-- Lokasyon Seçiniz --</option>';
    if (!whId) return;

    const wh = warehousesData.find(w => String(w.id) === String(whId));
    if (wh && wh.locations && wh.locations.length > 0) {
        wh.locations.forEach(loc => {
            const opt = document.createElement('option');
            opt.value = loc.id;
            opt.textContent = `${loc.code} - ${loc.name}`;
            locSelect.appendChild(opt);
        });
        // İlk lokasyonu otomatik seç
        locSelect.selectedIndex = 1;
    } else {
        locSelect.innerHTML = '<option value="">-- Bu depoda aktif lokasyon yok --</option>';
    }
}

function onItemChange(itemId) {
    const sel = document.getElementById('purchase_order_item_id');
    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;

    const ordered = parseFloat(opt.dataset.ordered) || 0;
    const received = parseFloat(opt.dataset.received) || 0;
    const remaining = parseFloat(opt.dataset.remaining) || 0;
    const unit = opt.dataset.unit || '';

    document.getElementById('disp_ordered').innerText = ordered.toLocaleString('tr-TR', { minimumFractionDigits: 2 });
    document.getElementById('disp_received').innerText = received.toLocaleString('tr-TR', { minimumFractionDigits: 2 });
    document.getElementById('disp_remaining').innerText = remaining.toLocaleString('tr-TR', { minimumFractionDigits: 2 });
    document.getElementById('disp_unit').innerText = unit;

    const qtyInput = document.getElementById('received_quantity');
    qtyInput.max = remaining;
    qtyInput.value = remaining;
}

function validateReceiptForm() {
    const qtyInput = document.getElementById('received_quantity');
    const val = parseFloat(qtyInput.value) || 0;
    const maxVal = parseFloat(qtyInput.max) || 0;

    if (val <= 0) {
        alert('Lütfen 0\'dan büyük bir teslimat miktarı giriniz.');
        qtyInput.focus();
        return false;
    }

    if (val > maxVal) {
        alert(`Teslim alınan miktar (${val}) kalan sipariş miktarından (${maxVal}) fazla olamaz.`);
        qtyInput.focus();
        return false;
    }

    const wh = document.getElementById('warehouse_id').value;
    const loc = document.getElementById('location_id').value;
    if (!wh || !loc) {
        alert('Lütfen geçerli bir depo ve lokasyon seçiniz.');
        return false;
    }

    return true;
}

// Sayfa yüklendiğinde varsayılan depoyu ve lokasyonları tetikle
document.addEventListener('DOMContentLoaded', function() {
    const whSelect = document.getElementById('warehouse_id');
    if (whSelect.value) {
        onWarehouseChange(whSelect.value);
    }
});
</script>

