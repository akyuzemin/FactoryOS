<?php
$pageTitle = 'API Anahtarları Yönetimi';
$activePage = 'api-tokens';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content" style="max-width: 1360px; padding: 24px 32px;">

    <!-- HEADER -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; background: #ffffff; padding: 20px 24px; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 22px;">🔑</span>
                <h1 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0;">API Anahtarları ve Entegrasyon</h1>
            </div>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 30px;">
                MES Worker servisleri, telemetri simülatörleri ve dış kurumsal sistemler için güvenli Bearer token yönetimi.
            </p>
        </div>
    </div>

    <!-- FLASH MESSAGE -->
    <?php if ($flashMessage): ?>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; color: #166534; font-size: 13.5px; font-weight: 600;">
            <?= htmlspecialchars($flashMessage) ?>
        </div>
    <?php endif; ?>

    <!-- NEW TOKEN GENERATED MODAL / NOTICE -->
    <?php if ($createdToken): ?>
        <div style="background: #eff6ff; border: 2px solid #3b82f6; border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(37,99,235,0.1);">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <span style="font-size: 20px;">🔒</span>
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #1e40af;">Yeni API Token Oluşturuldu</h3>
            </div>
            <p style="font-size: 13px; color: #1e3a8a; margin: 0 0 12px 0;">
                Bu token <strong>yalnızca bir kez</strong> gösterilir. Lütfen güvenli bir yerde saklayın veya ortam değişkenlerinize (<code>.env</code>) kaydedin.
            </p>
            
            <div style="background: #ffffff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                <code style="font-family: monospace; font-size: 14px; font-weight: 700; color: #0f172a; word-break: break-all;">
                    <?= htmlspecialchars($createdToken['plain_token']) ?>
                </code>
                <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($createdToken['plain_token']) ?>'); alert('Token panoya kopyalandı!');" class="button button-small" style="padding: 6px 14px; font-size: 12px; font-weight: 700; white-space: nowrap;">
                    📋 Kopyala
                </button>
            </div>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">

        <!-- TOKEN LIST TABLE -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0 0 16px 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                Aktif &amp; Kayıtlı Tokenlar
            </h3>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569;">İsim / Ön Ek</th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569;">Yetkiler (Scope)</th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569;">Son Kullanım</th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569;">Durum</th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #475569; text-align: right;">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tokens)): ?>
                            <tr>
                                <td colspan="5" style="padding: 20px; text-align: center; color: #64748b;">
                                    Henüz oluşturulmuş bir API token bulunmuyor.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tokens as $token): 
                                $perms = !empty($token['permissions']) ? json_decode($token['permissions'], true) : [];
                            ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; <?= (int)$token['is_active'] === 0 ? 'opacity: 0.5;' : '' ?>">
                                    <td style="padding: 12px;">
                                        <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($token['name']) ?></div>
                                        <code style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($token['token_prefix']) ?>...</code>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php if (empty($perms) || in_array('*', $perms, true)): ?>
                                            <span style="display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700; background: #e0e7ff; color: #3730a3;">Tam Yetki (*)</span>
                                        <?php else: ?>
                                            <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                                <?php foreach (array_slice($perms, 0, 3) as $p): ?>
                                                    <span style="padding: 1px 6px; border-radius: 4px; font-size: 10.5px; background: #f1f5f9; color: #475569; font-family: monospace;"><?= htmlspecialchars($p) ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($perms) > 3): ?>
                                                    <span style="font-size: 10.5px; color: #64748b;">+<?= count($perms) - 3 ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; font-size: 11.5px; color: #64748b;">
                                        <?= !empty($token['last_used_at']) ? date('d.m.Y H:i', strtotime($token['last_used_at'])) : 'Hiç kullanılmadı' ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php if ((int)$token['is_active'] === 1): ?>
                                            <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #dcfce7; color: #166534;">Aktif</span>
                                        <?php else: ?>
                                            <span style="display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: #fee2e2; color: #991b1b;">İptal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; text-align: right;">
                                        <?php if ((int)$token['is_active'] === 1): ?>
                                            <form method="POST" action="/stok-takip/public/api-tokens/revoke" onsubmit="return confirm('Bu tokeni iptal etmek istediğinize emin misiniz?');" style="display: inline;">
                                                <input type="hidden" name="token_id" value="<?= (int)$token['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= CsrfService::getToken() ?>">
                                                <button type="submit" class="button button-small" style="padding: 4px 8px; font-size: 11px; background: #fff1f2; color: #e11d48; border: 1px solid #fecdd3;">İptal Et</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CREATE NEW TOKEN FORM -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); align-self: flex-start;">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0 0 16px 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                Yeni Token Oluştur
            </h3>

            <form method="POST" action="/stok-takip/public/api-tokens/create">
                <input type="hidden" name="csrf_token" value="<?= CsrfService::getToken() ?>">
                
                <div style="margin-bottom: 14px;">
                    <label style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Token Adı / Açıklama</label>
                    <input type="text" name="name" placeholder="Örn: MES Worker Service Line 1" required style="width: 100%; padding: 8px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Kullanıcı Bağlantısı (Opsiyonel)</label>
                    <select name="user_id" style="width: 100%; padding: 8px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff;">
                        <option value="">Sistem / Bağımsız Token</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Son Geçerlilik Tarihi (Opsiyonel)</label>
                    <input type="date" name="expires_at" style="width: 100%; padding: 7px 10px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="font-size: 12px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Yetkiler (Scope)</label>
                    <div style="display: flex; flex-direction: column; gap: 6px; max-height: 180px; overflow-y: auto; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fafafa;">
                        <label style="font-size: 12px; display: flex; align-items: center; gap: 6px;">
                            <input type="checkbox" name="permissions[]" value="*" checked> <b>Tam Yetki (*)</b>
                        </label>
                        <label style="font-size: 12px; display: flex; align-items: center; gap: 6px;">
                            <input type="checkbox" name="permissions[]" value="production.execute"> MES Üretim Yürütme (production.execute)
                        </label>
                        <label style="font-size: 12px; display: flex; align-items: center; gap: 6px;">
                            <input type="checkbox" name="permissions[]" value="production.view"> Üretim İzleme (production.view)
                        </label>
                        <label style="font-size: 12px; display: flex; align-items: center; gap: 6px;">
                            <input type="checkbox" name="permissions[]" value="stock.view"> Stok İzleme (stock.view)
                        </label>
                        <label style="font-size: 12px; display: flex; align-items: center; gap: 6px;">
                            <input type="checkbox" name="permissions[]" value="quality.inspect"> Kalite Kontrol (quality.inspect)
                        </label>
                        <label style="font-size: 12px; display: flex; align-items: center; gap: 6px;">
                            <input type="checkbox" name="permissions[]" value="energy.view"> Enerji İzleme (energy.view)
                        </label>
                    </div>
                </div>

                <button type="submit" class="button button-primary" style="width: 100%; padding: 10px; font-size: 13.5px; font-weight: 700;">
                    + Token Oluştur
                </button>
            </form>
        </div>

    </div>

</main>

