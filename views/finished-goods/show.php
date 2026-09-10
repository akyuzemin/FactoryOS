<?php

$pageTitle = 'Panel Pasaportu: ' . $panel['serial_no'];

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$event = $bomDetails['event'] ?? [];
$bomItems = $bomDetails['bom_items'] ?? [];
$stockMovements = $stockMovements ?? [];
$unitCost = (float)($panel['unit_cost'] ?? 0);
$eventTotalCost = (float)($event['total_cost'] ?? 0);
$eventQty = (float)($event['quantity'] ?? 1);
$statusLabel = match ((string)($panel['status'] ?? 'IN_STOCK')) {
    'IN_STOCK' => '🟢 Stokta',
    'SHIPPED' => '🚚 Sevk Edildi',
    'QUALITY_PENDING' => '🟡 Kalite Bekliyor',
    'QUALITY_APPROVED' => '✅ Kalite Onaylı',
    'QUALITY_REJECTED' => '🔴 Reddedildi',
    'QUARANTINE' => '⚠️ Karantina',
    default => htmlspecialchars($panel['status']),
};
?>
<main class="main-content">

    <?php if (isset($_SESSION['success'])): ?>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
            <span>✅</span> <?= htmlspecialchars($_SESSION['success']) ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
            <span>⚠️</span> <?= htmlspecialchars($_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div style="margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #64748b; margin-bottom: 8px;">
            <a href="/stok-takip/public/finished-goods" style="color: #4338ca; text-decoration: none; font-weight: 600;">Panel Seri Takip</a>
            <span>&rsaquo;</span>
            <span style="color: #0f172a; font-weight: 600;"><?= htmlspecialchars($panel['serial_no']) ?></span>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
            <div>
                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 6px; background: #e0e7ff; color: #3730a3; font-weight: 800; font-size: 13px; font-family: monospace; border: 1px solid #c7d2fe; margin-bottom: 6px;">
                    <?= htmlspecialchars($panel['serial_no']) ?>
                </span>
                <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 6px 0; letter-spacing: -0.02em;">
                    <?= htmlspecialchars($panel['material_name']) ?>
                </h1>
                <p style="font-size: 13.5px; color: #64748b; margin: 0;">
                    Üretim Tarihi: <?= date('d.m.Y H:i:s', strtotime($panel['produced_at'])) ?>
                </p>
            </div>

            <div style="display: flex; gap: 8px; align-items: center;">
                <a class="button" href="/stok-takip/public/finished-goods" style="padding: 8px 14px; font-size: 13px; text-decoration: none;">
                    &larr; Listeye Dön
                </a>
            </div>
        </div>
    </div>

    <!-- DEPO HIZLI KONTROL & DURUM ÖZETİ (QR / BARKOD TARAMA ÖZETİ) -->
    <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-radius: 16px; padding: 20px 24px; margin-bottom: 24px; color: #ffffff; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.15);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 22px;">📱</span>
                <div>
                    <h3 style="font-size: 16px; font-weight: 800; color: #ffffff; margin: 0;">DEPO HIZLI KONTROL &amp; QR DURUM ÖZETİ</h3>
                    <p style="font-size: 12px; color: #94a3b8; margin: 2px 0 0 0;">Barkod / QR okuyucu ile taranan panel hızlı kontrol kartı</p>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" onclick="window.print();" class="button" style="background: #3b82f6; border: none; color: white; padding: 6px 14px; font-size: 12.5px; font-weight: 700; border-radius: 8px; cursor: pointer;">
                    🖨️ Etiket / QR Yazdır
                </button>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: center;">
            
            <!-- 1. Kalite Kontrol Rozeti -->
            <div style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); padding: 12px 16px; border-radius: 12px;">
                <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">🛡️ KALİTE DURUMU</div>
                <div>
                    <?php if (($panel['status'] ?? '') === 'QUALITY_APPROVED'): ?>
                        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 800; background: #22c55e; color: #ffffff;">
                            🟢 KALİTE ONAYLI
                        </span>
                    <?php elseif (($panel['status'] ?? '') === 'QUALITY_REJECTED'): ?>
                        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 800; background: #ef4444; color: #ffffff;">
                            🔴 REDDEDİLDİ
                        </span>
                    <?php else: ?>
                        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 800; background: #eab308; color: #0f172a;">
                            🟡 KALİTE BEKLİYOR
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. Depo & Lokasyon Rozeti -->
            <div style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); padding: 12px 16px; border-radius: 12px;">
                <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">🏢 DEPO &amp; LOKASYON</div>
                <div style="font-size: 13.5px; font-weight: 700; color: #ffffff;">
                    <?php if (($panel['status'] ?? '') === 'SHIPPED'): ?>
                        <span style="color: #cbd5e1;">🚚 Müşteriye Sevk Edildi</span>
                    <?php else: ?>
                        📦 <?= htmlspecialchars($panel['warehouse_name'] ?? 'Mamul Deposu') ?> 
                        <span style="font-size: 12px; color: #38bdf8;">(<?= htmlspecialchars($panel['location_code'] ?? 'A-01') ?>)</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3. Sevkiyat Durumu Rozeti -->
            <div style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); padding: 12px 16px; border-radius: 12px;">
                <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">🚚 SEVKİYAT DURUMU</div>
                <div>
                    <?php if ($shipmentInfo): ?>
                        <div style="font-size: 13px; font-weight: 800; color: #4ade80;">
                            🚚 SEVK EDİLDİ
                        </div>
                        <div style="font-size: 11px; color: #cbd5e1; margin-top: 2px;">
                            <?= htmlspecialchars($shipmentInfo['shipment_no']) ?> &bull; <?= htmlspecialchars($shipmentInfo['customer_name']) ?>
                        </div>
                    <?php else: ?>
                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; background: rgba(255,255,255,0.12); color: #f1f5f9;">
                            ⌛ SEVK EDİLMEDİ (Depoda)
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 4. QR Kod Görseli -->
            <div style="background: #ffffff; padding: 10px; border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 12px;">
                <div style="width: 70px; height: 70px;">
                    <?= $qrSvg ?>
                </div>
                <div>
                    <div style="font-size: 11px; font-weight: 800; color: #0f172a; font-family: monospace;">QR TARAMA KODU</div>
                    <div style="font-size: 10px; color: #64748b; margin-top: 2px; font-family: monospace; word-break: break-all; max-width: 110px;">
                        <?= htmlspecialchars($panel['serial_no']) ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- PASSPORT CARD -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
            
            <!-- Sol: Üretim ve Mamul Bilgileri -->
            <div>
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 16px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                    🏭 Üretim Bilgileri
                </h3>
                <table style="width: 100%; font-size: 13.5px; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Seri Numarası:</td>
                        <td style="padding: 8px 0; font-family: monospace; font-weight: 800; color: #4338ca; text-align: right;"><?= htmlspecialchars($panel['serial_no']) ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Ürün Kodu &amp; Adı:</td>
                        <td style="padding: 8px 0; font-weight: 700; color: #0f172a; text-align: right;"><?= htmlspecialchars($panel['material_code']) ?> &bull; <?= htmlspecialchars($panel['material_name']) ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 8px 0; color: #64748b; font-weight: 600;">İş Emri No:</td>
                        <td style="padding: 8px 0; text-align: right;">
                            <?php if (!empty($panel['work_order_no'])): ?>
                                <a href="/stok-takip/public/mes/work-orders/show?id=<?= (int)$panel['work_order_id'] ?>" style="font-weight: 700; color: #2563eb; text-decoration: none;">
                                    <?= htmlspecialchars($panel['work_order_no']) ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Üretim Hattı:</td>
                        <td style="padding: 8px 0; color: #0f172a; text-align: right;"><?= htmlspecialchars($panel['line_name'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Stok Giriş Ref:</td>
                        <td style="padding: 8px 0; font-family: monospace; font-size: 12px; color: #475569; text-align: right;"><?= htmlspecialchars($panel['stock_movement_ref'] ?? '-') ?></td>
                    </tr>
                </table>
            </div>

            <!-- Sağ: Depo & Kalite Durumu -->
            <div>
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 16px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
                    🏢 Depolama &amp; Durum
                </h3>
                <table style="width: 100%; font-size: 13.5px; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Mevcut Depo:</td>
                        <td style="padding: 8px 0; font-weight: 700; color: #0f172a; text-align: right;"><?= htmlspecialchars($panel['warehouse_name'] ?? '-') ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Lokasyon / Raf:</td>
                        <td style="padding: 8px 0; font-weight: 700; color: #0f172a; text-align: right;"><?= htmlspecialchars($panel['location_name'] ?? '-') ?> (<?= htmlspecialchars($panel['location_code'] ?? '-') ?>)</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Durum:</td>
                        <td style="padding: 8px 0; text-align: right;">
                            <?php
                            $badgeStyle = match ((string)($panel['status'] ?? 'IN_STOCK')) {
                                'IN_STOCK', 'QUALITY_APPROVED' => 'background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;',
                                'QUALITY_PENDING' => 'background: #fef9c3; color: #854d0e; border: 1px solid #fef08a;',
                                'QUALITY_REJECTED' => 'background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;',
                                'QUARANTINE' => 'background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa;',
                                'SHIPPED' => 'background: #f8fafc; color: #475569; border: 1px solid #cbd5e1;',
                                default => 'background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;'
                            };
                            ?>
                            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 700; <?= $badgeStyle ?>">
                                <?= $statusLabel ?>
                            </span>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Kayıt Zamanı:</td>
                        <td style="padding: 8px 0; color: #475569; text-align: right;"><?= date('d.m.Y H:i:s', strtotime($panel['created_at'])) ?></td>
                    </tr>
                </table>
            </div>

        </div>
    </div>

    <!-- İMALAT MALİYETİ & ENERJİ KIRILIMI KARTI -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    💰 Toplam İmalat Maliyet Kırılımı (BOM Snapshot + Enerji SEC)
                </h3>
                <p style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">
                    Üretim anındaki hammadde (BOM) maliyet snapshot'ı ve hat/gün ortalama Spesifik Enerji Tüketimi (SEC)
                </p>
            </div>
            <span style="background: #f8fafc; border: 1px solid #cbd5e1; font-size: 11.5px; font-weight: 700; color: #475569; padding: 4px 10px; border-radius: 6px;">
                🛡️ Tarihsel Snapshot Korunuyor
            </span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
            
            <!-- 1. BOM Hammadde Maliyeti Snapshot -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px;">
                <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">1. HAMMADDE (BOM) MALİYETİ</div>
                <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 4px 0 2px 0;">
                    ₺<?= number_format((float)($energyCostBreakdown['bom_unit_cost'] ?? 0), 2, ',', '.') ?>
                </div>
                <div style="font-size: 11px; color: #64748b;">Üretim anındaki sabit BOM snapshot'ı</div>
            </div>

            <!-- 2. Spesifik Enerji Tüketimi (SEC) -->
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px 16px;">
                <div style="font-size: 11px; font-weight: 700; color: #166534; text-transform: uppercase;">2. ORTALAMA ENERJİ / PANEL (SEC)</div>
                <div style="font-size: 18px; font-weight: 800; color: #15803d; margin: 4px 0 2px 0;">
                    ₺<?= number_format((float)($energyCostBreakdown['estimated_energy_cost_tl'] ?? 0), 2, ',', '.') ?>
                </div>
                <div style="font-size: 11px; color: #166534;">
                    ⚡ Ortalama <?= number_format((float)($energyCostBreakdown['estimated_sec_kwh'] ?? 0), 2, ',', '.') ?> kWh / panel
                </div>
            </div>

            <!-- 3. Toplam Birim İmalat Maliyeti -->
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 14px 16px;">
                <div style="font-size: 11px; font-weight: 700; color: #1e40af; text-transform: uppercase;">= TOPLAM İMALAT MALİYETİ</div>
                <div style="font-size: 20px; font-weight: 900; color: #1d4ed8; margin: 4px 0 2px 0;">
                    ₺<?= number_format((float)($energyCostBreakdown['total_manufacturing_cost'] ?? 0), 2, ',', '.') ?>
                </div>
                <div style="font-size: 11px; color: #1e40af;">Hammadde + Ortalama Hat Enerjisi</div>
            </div>

        </div>
    </div>
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 16px;">
            <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;">
                🛡️ Kalite Kontrol Takip &amp; Muayene Kaydı
            </h3>
            <?php 
            $quality = null;
            if (!empty($panel['quality_notes'])) {
                $quality = json_decode($panel['quality_notes'], true);
            }
            ?>
            <?php if ($quality): ?>
                <button type="button" onclick="document.getElementById('qc_result_section').style.display='none'; document.getElementById('qc_form_section').style.display='block';" class="button button-small" style="padding: 4px 10px; font-size: 12px; font-weight: 600; cursor: pointer;">
                    Muayeneyi Yenile / Yeniden Test Et
                </button>
            <?php endif; ?>
        </div>

        <!-- KALİTE SONUÇLARI GÖRÜNÜMÜ -->
        <div id="qc_result_section" style="display: <?= $quality ? 'block' : 'none' ?>;">
            <?php if ($quality): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 20px;">
                    <div>
                        <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px;">Muayene Eden:</div>
                        <div style="font-weight: 700; color: #0f172a; font-size: 14px;"><?= htmlspecialchars($quality['checked_by'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px;">Muayene Zamanı:</div>
                        <div style="font-weight: 700; color: #0f172a; font-size: 14px;"><?= !empty($quality['checked_at']) ? date('d.m.Y H:i:s', strtotime($quality['checked_at'])) : '-' ?></div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px;">Genel Kalite Durumu:</div>
                        <div>
                            <?php if (($panel['status'] ?? '') === 'QUALITY_APPROVED'): ?>
                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                                    ✅ ONAYLANDI
                                </span>
                            <?php elseif (($panel['status'] ?? '') === 'QUALITY_REJECTED'): ?>
                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">
                                    🔴 REDDEDİLDİ
                                </span>
                            <?php else: ?>
                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700; background: #fef9c3; color: #854d0e; border: 1px solid #fef08a;">
                                    🟡 KALİTE BEKLİYOR
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 16px;">
                    <div style="font-weight: 700; font-size: 13px; color: #334155; margin-bottom: 12px;">📊 Test Sonuç Kırılımları:</div>
                    <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                            <span>👁️ Görsel (Visual) Muayene:</span>
                            <span style="font-weight: 700; color: <?= ($quality['visual_inspection'] ?? '') === 'PASS' ? '#16a34a' : (($quality['visual_inspection'] ?? '') === 'FAIL' ? '#dc2626' : '#d97706') ?>;">
                                <?= htmlspecialchars($quality['visual_inspection'] ?? 'PENDING') ?>
                            </span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                            <span>⚡ EL Testi (Elektrominesans):</span>
                            <span style="font-weight: 700; color: <?= ($quality['el_test'] ?? '') === 'PASS' ? '#16a34a' : (($quality['el_test'] ?? '') === 'FAIL' ? '#dc2626' : '#d97706') ?>;">
                                <?= htmlspecialchars($quality['el_test'] ?? 'PENDING') ?>
                            </span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                            <span>☀️ Flash Testi (Performans):</span>
                            <span style="font-weight: 700; color: <?= ($quality['flash_test'] ?? '') === 'PASS' ? '#16a34a' : (($quality['flash_test'] ?? '') === 'FAIL' ? '#dc2626' : '#d97706') ?>;">
                                <?= htmlspecialchars($quality['flash_test'] ?? 'PENDING') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if (($panel['status'] ?? '') === 'QUALITY_REJECTED' && !empty($quality['rejection_reason'])): ?>
                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 16px; margin-bottom: 16px;">
                        <div style="font-weight: 700; font-size: 13px; color: #991b1b; margin-bottom: 4px;">❌ Red Nedeni:</div>
                        <div style="font-size: 13.5px; color: #7f1d1d; font-weight: 600;"><?= htmlspecialchars($quality['rejection_reason']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($quality['notes'])): ?>
                    <div>
                        <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px;">Açıklama / Kalite Notları:</div>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; font-size: 13px; color: #334155; line-height: 1.5; white-space: pre-wrap;"><?= htmlspecialchars($quality['notes']) ?></div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 10px 0; color: #64748b;">
                    <p style="font-size: 13.5px; margin: 0 0 12px 0;">Bu panele ait kalite kontrol kaydı bulunmamaktadır.</p>
                    <button type="button" onclick="document.getElementById('qc_result_section').style.display='none'; document.getElementById('qc_form_section').style.display='block';" class="button button-primary" style="padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer;">
                        ➕ Kalite Kontrol Muayenesi Yap
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- KALİTE KONTROL FORMU -->
        <div id="qc_form_section" style="display: <?= $quality ? 'none' : 'block' ?>;">
            <form method="POST" id="qc_form" action="/stok-takip/public/finished-goods/quality-control/save" onsubmit="return validateQCForm(event)">
            <?= CsrfService::tokenField() ?>
                <input type="hidden" name="panel_id" value="<?= (int)$panel['id'] ?>">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="display: block; font-weight: 700; font-size: 12.5px; color: #374151; margin-bottom: 6px;">👁️ Görsel Muayene (Visual):</label>
                        <select name="visual_inspection" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                            <option value="PASS" <?= ($quality['visual_inspection'] ?? 'PASS') === 'PASS' ? 'selected' : '' ?>>PASS (Başarılı)</option>
                            <option value="FAIL" <?= ($quality['visual_inspection'] ?? '') === 'FAIL' ? 'selected' : '' ?>>FAIL (Hatalı)</option>
                            <option value="PENDING" <?= ($quality['visual_inspection'] ?? '') === 'PENDING' ? 'selected' : '' ?>>PENDING (Beklemede)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 700; font-size: 12.5px; color: #374151; margin-bottom: 6px;">⚡ EL Testi (Elektrominesans):</label>
                        <select name="el_test" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                            <option value="PASS" <?= ($quality['el_test'] ?? 'PASS') === 'PASS' ? 'selected' : '' ?>>PASS (Başarılı)</option>
                            <option value="FAIL" <?= ($quality['el_test'] ?? '') === 'FAIL' ? 'selected' : '' ?>>FAIL (Hatalı)</option>
                            <option value="PENDING" <?= ($quality['el_test'] ?? '') === 'PENDING' ? 'selected' : '' ?>>PENDING (Beklemede)</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 700; font-size: 12.5px; color: #374151; margin-bottom: 6px;">☀️ Flash Testi (Performans):</label>
                        <select name="flash_test" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                            <option value="PASS" <?= ($quality['flash_test'] ?? 'PASS') === 'PASS' ? 'selected' : '' ?>>PASS (Başarılı)</option>
                            <option value="FAIL" <?= ($quality['flash_test'] ?? '') === 'FAIL' ? 'selected' : '' ?>>FAIL (Hatalı)</option>
                            <option value="PENDING" <?= ($quality['flash_test'] ?? '') === 'PENDING' ? 'selected' : '' ?>>PENDING (Beklemede)</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 700; font-size: 12.5px; color: #374151; margin-bottom: 6px;">🛡️ Genel Kalite Durumu:</label>
                    <select name="status" id="qc_status_select" onchange="toggleRejectionReason(this.value)" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 700;">
                        <option value="QUALITY_PENDING" <?= ($panel['status'] ?? '') === 'QUALITY_PENDING' ? 'selected' : '' ?>>🟡 KALİTE BEKLİYOR</option>
                        <option value="QUALITY_APPROVED" <?= ($panel['status'] ?? 'QUALITY_APPROVED') === 'QUALITY_APPROVED' || ($panel['status'] ?? '') === 'IN_STOCK' ? 'selected' : '' ?>>✅ ONAYLANDI (Stoka Al)</option>
                        <option value="QUALITY_REJECTED" <?= ($panel['status'] ?? '') === 'QUALITY_REJECTED' ? 'selected' : '' ?>>🔴 REDDEDİLDİ</option>
                    </select>
                </div>

                <div id="rejection_reason_container" style="display: <?= ($panel['status'] ?? '') === 'QUALITY_REJECTED' ? 'block' : 'none' ?>; margin-bottom: 16px;">
                    <label style="display: block; font-weight: 700; font-size: 12.5px; color: #b91c1c; margin-bottom: 6px;">❌ Red Nedeni (Zorunlu):</label>
                    <input type="text" name="rejection_reason" id="rejection_reason_input" value="<?= htmlspecialchars($quality['rejection_reason'] ?? '') ?>" placeholder="Örn: Hücre çatlağı, çerçeve çizilmesi vb." style="width: 100%; padding: 8px 12px; border: 1px solid #fca5a5; border-radius: 8px; font-size: 13px; background: #fff5f5;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 700; font-size: 12.5px; color: #374151; margin-bottom: 6px;">📝 Kalite Açıklama / Notlar:</label>
                    <textarea name="notes" placeholder="Ekstra test veya görsel muayene notları..." style="width: 100%; height: 80px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; resize: vertical;"><?= htmlspecialchars($quality['notes'] ?? ($panel['quality_notes'] ?? '')) ?></textarea>
                </div>

                <div style="display: flex; gap: 8px; align-items: center;">
                    <button type="submit" class="button button-primary" style="padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer; background: #4338ca; border: none; border-radius: 8px; color: white;">
                        Kaydet ve Durumu Güncelle
                    </button>
                    <?php if ($quality): ?>
                        <button type="button" onclick="document.getElementById('qc_form_section').style.display='none'; document.getElementById('qc_result_section').style.display='block';" class="button" style="padding: 8px 14px; font-size: 13px;">
                            İptal
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <script>
    function toggleRejectionReason(status) {
        var container = document.getElementById('rejection_reason_container');
        var input = document.getElementById('rejection_reason_input');
        if (status === 'QUALITY_REJECTED') {
            container.style.display = 'block';
            input.setAttribute('required', 'required');
        } else {
            container.style.display = 'none';
            input.removeAttribute('required');
        }
    }
    
    function validateQCForm(event) {
        var status = document.getElementById('qc_status_select').value;
        var reasonInput = document.getElementById('rejection_reason_input');
        var reason = reasonInput.value.trim();
        
        if (status === 'QUALITY_REJECTED' && reason === '') {
            alert('Lütfen panel reddedildiğinde bir red nedeni giriniz.');
            reasonInput.focus();
            event.preventDefault();
            return false;
        }
        return true;
    }

    // Set initial required state
    toggleRejectionReason(document.getElementById('qc_status_select').value);
    </script>

    <!-- ÜRETİM BİLGİSİ -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
        <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 16px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
            ⚙️ Üretim Bilgisi
        </h3>
        <table style="width: 100%; font-size: 13.5px; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Event ID:</td>
                <td style="padding: 8px 0; font-family: monospace; color: #0f172a; text-align: right;"><?= htmlspecialchars($event['event_id'] ?? '-') ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Event Zamanı:</td>
                <td style="padding: 8px 0; color: #0f172a; text-align: right;"><?= !empty($event['event_time']) ? date('d.m.Y H:i:s', strtotime($event['event_time'])) : '-' ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Event Tipi:</td>
                <td style="padding: 8px 0; color: #0f172a; text-align: right;"><?= htmlspecialchars($event['event_type'] ?? '-') ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Kaynak:</td>
                <td style="padding: 8px 0; color: #0f172a; text-align: right;"><?= htmlspecialchars($event['source'] ?? '-') ?></td>
            </tr>
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Reçete:</td>
                <td style="padding: 8px 0; color: #0f172a; text-align: right;"><?= htmlspecialchars($event['recipe_code'] ?? '-') ?> — <?= htmlspecialchars($event['recipe_name'] ?? '-') ?></td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #64748b; font-weight: 600;">Event Durumu:</td>
                <td style="padding: 8px 0; color: #0f172a; text-align: right;"><?= htmlspecialchars($event['status'] ?? '-') ?></td>
            </tr>
        </table>
    </div>

    <!-- BOM / HAMMADDE -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
        <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 16px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
            📦 BOM / Hammadde
        </h3>
        <?php if (empty($bomItems)): ?>
            <p style="color: #64748b; font-size: 13px;">Bu event için BOM detayı bulunamadı.</p>
        <?php else: ?>
            <div style="overflow-x: auto; border: 1px solid #f1f5f9; border-radius: 8px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">
                            <th style="padding: 10px 14px;">MALZEME</th>
                            <th style="padding: 10px 14px; text-align: right;">PANEL BAŞINA</th>
                            <th style="padding: 10px 14px; text-align: right;">BİRİM FİYAT</th>
                            <th style="padding: 10px 14px; text-align: right;">PANEL MALİYETİ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bomItems as $bItem): 
                            $effectiveQty = (float)($bItem['effective_qty'] ?? 0);
                            $unitPrice = (float)($bItem['unit_price'] ?? 0);
                            $itemTotal = round($effectiveQty * $unitPrice, 4);
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 12px 14px;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 13px;">
                                        <?= htmlspecialchars($bItem['material_name']) ?>
                                    </div>
                                    <span style="font-size: 11px; color: #64748b; font-family: monospace;">
                                        <?= htmlspecialchars($bItem['material_code']) ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 600; color: #334155; font-family: monospace;">
                                    <?= number_format($effectiveQty, 2, ',', '.') ?> <?= htmlspecialchars($bItem['unit_symbol'] ?? 'AD') ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 600; color: #64748b; font-family: monospace;">
                                    <?= number_format($unitPrice, 2, ',', '.') ?> TL
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 700; color: #0f172a; font-family: monospace;">
                                    <?= number_format($itemTotal, 2, ',', '.') ?> TL
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f8fafc;">
                            <td colspan="3" style="padding: 10px 14px; font-weight: 700; color: #334155; text-align: right;">BOM Toplam (Bu Panel):</td>
                            <td style="padding: 10px 14px; font-weight: 800; color: #0f172a; font-family: monospace;">
                                <?= number_format(array_sum(array_map(fn($it) => round((float)($it['effective_qty'] ?? 0) * (float)($it['unit_price'] ?? 0), 4), $bomItems)), 2, ',', '.') ?> TL
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- STOK HAREKETLERİ -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
        <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 16px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
            📋 Stok Hareketleri
        </h3>
        <?php if (empty($stockMovements)): ?>
            <p style="color: #64748b; font-size: 13px;">Bu panele ait stok hareketi bulunamadı.</p>
        <?php else: ?>
            <div style="overflow-x: auto; border: 1px solid #f1f5f9; border-radius: 8px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">
                            <th style="padding: 10px 14px;">TARİH</th>
                            <th style="padding: 10px 14px;">MALZEME</th>
                            <th style="padding: 10px 14px; text-align: center;">HAREKET</th>
                            <th style="padding: 10px 14px; text-align: right;">MİKTAR</th>
                            <th style="padding: 10px 14px; text-align: right;">BİRİM FİYAT</th>
                            <th style="padding: 10px 14px; text-align: right;">TOPLAM</th>
                            <th style="padding: 10px 14px;">DEPO / LOKASYON</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockMovements as $sm): 
                            $moveType = strtoupper((string)($sm['movement_type'] ?? ''));
                            $moveBadge = match ($moveType) {
                                'IN' => ['bg' => '#f0fdf4', 'color' => '#166534', 'border' => '#bbf7d0', 'icon' => '↓'],
                                'OUT' => ['bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fecaca', 'icon' => '↑'],
                                default => ['bg' => '#f8fafc', 'color' => '#475569', 'border' => '#e2e8f0', 'icon' => '='],
                            };
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 10px 14px; color: #64748b; font-size: 12px; font-family: monospace; white-space: nowrap;">
                                    <?= date('d.m.Y H:i', strtotime($sm['created_at'])) ?>
                                </td>
                                <td style="padding: 10px 14px;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 13px;"><?= htmlspecialchars($sm['material_name'] ?? '-') ?></div>
                                    <span style="font-size: 11px; color: #64748b; font-family: monospace;"><?= htmlspecialchars($sm['material_code'] ?? '-') ?></span>
                                </td>
                                <td style="padding: 10px 14px; text-align: center;">
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: <?= $moveBadge['bg'] ?>; color: <?= $moveBadge['color'] ?>; border: 1px solid <?= $moveBadge['border'] ?>;">
                                        <?= $moveBadge['icon'] ?> <?= htmlspecialchars($moveType) ?>
                                    </span>
                                </td>
                                <td style="padding: 10px 14px; text-align: right; font-weight: 700; color: #0f172a; font-family: monospace;">
                                    <?= number_format((float)($sm['quantity'] ?? 0), 2, ',', '.') ?> <?= htmlspecialchars($sm['unit_symbol'] ?? 'AD') ?>
                                </td>
                                <td style="padding: 10px 14px; text-align: right; font-weight: 600; color: #64748b; font-family: monospace;">
                                    <?= number_format((float)($sm['unit_price'] ?? 0), 2, ',', '.') ?> TL
                                </td>
                                <td style="padding: 10px 14px; text-align: right; font-weight: 700; color: #0f172a; font-family: monospace;">
                                    <?= number_format((float)($sm['total_price'] ?? 0), 2, ',', '.') ?> TL
                                </td>
                                <td style="padding: 10px 14px; font-size: 12px; color: #475569;">
                                    <?= htmlspecialchars($sm['warehouse_name'] ?? '-') ?> / <?= htmlspecialchars($sm['location_name'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</main>
