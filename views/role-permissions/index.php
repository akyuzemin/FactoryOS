<?php
$pageTitle = 'Rol &amp; Yetki Yönetimi';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';

$isAdminRole = ($currentRole['id'] == 1);
$lockedAdminPerms = ['dashboard.view', 'user.view', 'user.manage'];
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Sistem Güvenliği &amp; Erişim Kontrolü (RBAC)</p>
            <h1>🛡️ Rol &amp; Yetki Yönetimi</h1>
            <p class="page-description">Rollerin sistem içi erişim izinlerini modül bazında yönetin.</p>
        </div>
        <div class="header-badges" style="display: flex; gap: 10px; align-items: center;">
            <form method="POST" action="/stok-takip/public/role-permissions/reset" id="reset-defaults-form" onsubmit="return confirm('<?= htmlspecialchars($currentRole['name']) ?> rolünün yetkileri varsayılan ayarlarına döndürülecek. Devam etmek istiyor musunuz?');" style="margin: 0;">
            <?= CsrfService::tokenField() ?>
                <input type="hidden" name="role_id" value="<?= (int)$currentRole['id'] ?>">
                <button type="submit" class="button button-secondary button-sm" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 500; color: #cbd5e1; padding: 6px 12px;">
                    <span>↩</span> Varsayılana Dön
                </button>
            </form>
            <a href="/stok-takip/public/users" class="button button-secondary button-sm">
                &larr; Kullanıcılar
            </a>
        </div>
    </header>

    <!-- FLASH BİLDİRİM MESAJLARI -->
    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
            <span>✓ <?= $flashSuccess ?></span>
            <button type="button" onclick="this.parentElement.remove();" style="background:none; border:none; color:#34d399; cursor:pointer; font-weight:bold; font-size:16px;">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
            <span>⚠️ <?= htmlspecialchars($flashError) ?></span>
            <button type="button" onclick="this.parentElement.remove();" style="background:none; border:none; color:#f87171; cursor:pointer; font-weight:bold; font-size:16px;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- 1. ROL SEÇİM SEKMELERİ (PILLS) -->
    <div class="card" style="padding: 16px 20px; margin-bottom: 20px;">
        <span style="font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 10px;">
            YÖNETİLECEK ROLÜ SEÇİN
        </span>

        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <?php foreach ($roles as $r): 
                $isActiveRole = ((int)$r['id'] === (int)$currentRole['id']);
                $roleColor = match((int)$r['id']) {
                    1 => 'var(--purple)',
                    2 => 'var(--cyan)',
                    3 => 'var(--amber)',
                    4 => 'var(--green)',
                    default => 'var(--ink)'
                };
            ?>
                <a href="/stok-takip/public/role-permissions?role_id=<?= $r['id'] ?>" 
                   class="button button-sm <?= $isActiveRole ? 'button-primary' : 'button-secondary' ?>"
                   style="<?= $isActiveRole ? 'font-weight: 700; box-shadow: 0 0 12px rgba(139, 92, 246, 0.35);' : '' ?> padding: 8px 16px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: <?= $roleColor ?>;"></span>
                    <?= htmlspecialchars($r['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.06); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div>
                <strong style="color: var(--ink); font-size: 13.5px;"><?= htmlspecialchars($currentRole['name']) ?></strong>
                <span style="color: var(--text-secondary); font-size: 12px; margin-left: 8px;">
                    <?= htmlspecialchars($currentRole['description'] ?? '') ?>
                </span>
            </div>
            <div>
                <?php $totalSystemPermCount = array_sum(array_map(static fn($g) => count($g['permissions']), $groupedPermissions)); ?>
                <span class="status-badge status-badge-info" style="font-size: 11px;">
                    Aktif İzin: <strong><?= count($assignedPermissionIds) ?></strong> / <?= $totalSystemPermCount ?>
                </span>
            </div>
        </div>
    </div>

    <!-- 2. HIZLI SEÇİM & CANLI YANSIMA KONTROLÜ -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; font-size: 12.5px; color: var(--text-secondary);">
        <span>ℹ️ Yetki değişiklikleri kaydedildiği anda bu roldeki tüm kullanıcılar için anında yürürlüğe girer.</span>
        <div style="display: flex; gap: 8px;">
            <button type="button" class="button button-sm button-secondary" style="font-size: 11px; padding: 4px 10px;" onclick="toggleAllCheckboxes(true)">
                Tümünü Seç
            </button>
            <button type="button" class="button button-sm button-secondary" style="font-size: 11px; padding: 4px 10px;" onclick="toggleAllCheckboxes(false)">
                Temizle
            </button>
        </div>
    </div>

    <!-- 3. YETKİLERİ GÜNCELLEME FORMU (COLLAPSIBLE MODÜLLER) -->
    <form method="POST" action="/stok-takip/public/role-permissions/update" id="permissions-form">
        <?= CsrfService::tokenField() ?>
        <input type="hidden" name="role_id" value="<?= (int)$currentRole['id'] ?>">

        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 30px;">
            <?php 
            $permLabels = RolePermission::getPermissionLabels();
            foreach ($groupedPermissions as $groupName => $group): 
                $groupPerms = $group['permissions'];
                if (empty($groupPerms)) continue;

                $assignedInGroup = 0;
                foreach ($groupPerms as $p) {
                    if (in_array((int)$p['id'], $assignedPermissionIds, true)) {
                        $assignedInGroup++;
                    }
                }
            ?>
                <details class="card" open style="padding: 14px 18px;">
                    <summary style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; list-style: none; user-select: none;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 18px;"><?= $group['icon'] ?></span>
                            <strong style="font-size: 14px; color: var(--ink); margin: 0;"><?= htmlspecialchars($groupName) ?></strong>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="status-badge <?= $assignedInGroup > 0 ? 'status-badge-info' : 'status-badge-inactive' ?>" style="font-size: 10.5px; padding: 2px 7px;">
                                <?= $assignedInGroup ?> / <?= count($groupPerms) ?> Yetkili
                            </span>
                            <span style="font-size: 11px; color: var(--text-muted);">▾</span>
                        </div>
                    </summary>

                    <?php if ($groupName === 'Satın Alma & Tedarik'): 
                        $prPerms = array_filter($groupPerms, static fn($p) => in_array($p['name'], ['purchase.view', 'purchase.request', 'purchase.approve'], true));
                        $poPerms = array_filter($groupPerms, static fn($p) => str_starts_with($p['name'], 'purchase.order.'));
                    ?>
                        <!-- Satın Alma Talepleri Alt Bölümü -->
                        <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.06);">
                            <span style="font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 8px;">
                                📋 Satın Alma Talepleri (PR)
                            </span>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 8px;">
                                <?php foreach ($prPerms as $perm): 
                                    $permId = (int)$perm['id'];
                                    $permCode = $perm['name'];
                                    $isAssigned = in_array($permId, $assignedPermissionIds, true);
                                    $isLocked = ($isAdminRole && in_array($permCode, $lockedAdminPerms, true));
                                    $label = $permLabels[$permCode] ?? $perm['description'] ?: $permCode;
                                ?>
                                    <div style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: var(--radius-sm); background: <?= $isAssigned ? 'rgba(139, 92, 246, 0.06)' : 'rgba(255, 255, 255, 0.02)' ?>; border: 1px solid <?= $isAssigned ? 'rgba(139, 92, 246, 0.2)' : 'rgba(255, 255, 255, 0.04)' ?>;">
                                        <?php if ($isLocked): ?>
                                            <input type="hidden" name="permissions[]" value="<?= $permId ?>">
                                            <input type="checkbox" checked disabled style="accent-color: var(--purple); cursor: not-allowed;">
                                        <?php else: ?>
                                            <input type="checkbox" name="permissions[]" value="<?= $permId ?>" 
                                                   id="perm_<?= $permId ?>" 
                                                   class="perm-checkbox"
                                                   <?= $isAssigned ? 'checked' : '' ?>
                                                   style="accent-color: var(--purple); cursor: pointer;">
                                        <?php endif; ?>
                                        <label for="perm_<?= $permId ?>" style="cursor: <?= $isLocked ? 'default' : 'pointer' ?>; font-size: 12.5px; font-weight: 500; color: var(--ink); margin: 0; flex: 1;">
                                            <?= htmlspecialchars($label) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Satın Alma Siparişleri Alt Bölümü -->
                        <div style="margin-top: 14px; padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.06);">
                            <span style="font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 8px;">
                                📦 Satın Alma Siparişleri (PO)
                            </span>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 8px;">
                                <?php foreach ($poPerms as $perm): 
                                    $permId = (int)$perm['id'];
                                    $permCode = $perm['name'];
                                    $isAssigned = in_array($permId, $assignedPermissionIds, true);
                                    $isLocked = ($isAdminRole && in_array($permCode, $lockedAdminPerms, true));
                                    $label = $permLabels[$permCode] ?? $perm['description'] ?: $permCode;
                                ?>
                                    <div style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: var(--radius-sm); background: <?= $isAssigned ? 'rgba(139, 92, 246, 0.06)' : 'rgba(255, 255, 255, 0.02)' ?>; border: 1px solid <?= $isAssigned ? 'rgba(139, 92, 246, 0.2)' : 'rgba(255, 255, 255, 0.04)' ?>;">
                                        <?php if ($isLocked): ?>
                                            <input type="hidden" name="permissions[]" value="<?= $permId ?>">
                                            <input type="checkbox" checked disabled style="accent-color: var(--purple); cursor: not-allowed;">
                                        <?php else: ?>
                                            <input type="checkbox" name="permissions[]" value="<?= $permId ?>" 
                                                   id="perm_<?= $permId ?>" 
                                                   class="perm-checkbox"
                                                   <?= $isAssigned ? 'checked' : '' ?>
                                                   style="accent-color: var(--purple); cursor: pointer;">
                                        <?php endif; ?>
                                        <label for="perm_<?= $permId ?>" style="cursor: <?= $isLocked ? 'default' : 'pointer' ?>; font-size: 12.5px; font-weight: 500; color: var(--ink); margin: 0; flex: 1;">
                                            <?= htmlspecialchars($label) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Standart İzin Checkbox Grid -->
                        <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.06); display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 8px;">
                            <?php foreach ($groupPerms as $perm): 
                                $permId = (int)$perm['id'];
                                $permCode = $perm['name'];
                                $isAssigned = in_array($permId, $assignedPermissionIds, true);
                                $isLocked = ($isAdminRole && in_array($permCode, $lockedAdminPerms, true));
                                $label = $permLabels[$permCode] ?? $perm['description'] ?: $permCode;
                            ?>
                                <div style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: var(--radius-sm); background: <?= $isAssigned ? 'rgba(139, 92, 246, 0.06)' : 'rgba(255, 255, 255, 0.02)' ?>; border: 1px solid <?= $isAssigned ? 'rgba(139, 92, 246, 0.2)' : 'rgba(255, 255, 255, 0.04)' ?>;">
                                    
                                    <?php if ($isLocked): ?>
                                        <input type="hidden" name="permissions[]" value="<?= $permId ?>">
                                        <input type="checkbox" checked disabled style="accent-color: var(--purple); cursor: not-allowed;">
                                    <?php else: ?>
                                        <input type="checkbox" name="permissions[]" value="<?= $permId ?>" 
                                               id="perm_<?= $permId ?>" 
                                               class="perm-checkbox"
                                               <?= $isAssigned ? 'checked' : '' ?>
                                               style="accent-color: var(--purple); cursor: pointer;">
                                    <?php endif; ?>

                                    <div style="flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                        <label for="perm_<?= $permId ?>" style="cursor: <?= $isLocked ? 'default' : 'pointer' ?>; font-size: 12.5px; font-weight: 500; color: var(--ink); margin: 0;">
                                            <?= htmlspecialchars($label) ?>
                                        </label>
                                        <?php if ($isLocked): ?>
                                            <span class="status-badge status-badge-warning" style="font-size: 9.5px; padding: 1px 5px;">🔒 Zorunlu</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        </div>

        <!-- 4. KAYDET AKSİYON ÇUBUĞU -->
        <div class="card" style="padding: 14px 20px; position: sticky; bottom: 20px; display: flex; align-items: center; justify-content: space-between; z-index: 100; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4); border-color: rgba(139, 92, 246, 0.3);">
            <div style="font-size: 13px; color: var(--text-secondary);">
                Düzenlenen Rol: <strong style="color: var(--ink);"><?= htmlspecialchars($currentRole['name']) ?></strong>
            </div>

            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="button button-secondary button-sm" onclick="document.getElementById('reset-defaults-form').requestSubmit();" style="color: #cbd5e1;">
                    ↩ Varsayılana Dön
                </button>
                <button type="submit" class="button button-primary" style="padding: 8px 20px; font-size: 13.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    <span>💾</span> Yetkileri Kaydet
                </button>
            </div>
        </div>
    </form>

</main>

<script>
function toggleAllCheckboxes(state) {
    var checkboxes = document.querySelectorAll('.perm-checkbox');
    checkboxes.forEach(function(cb) {
        if (!cb.disabled) {
            cb.checked = state;
        }
    });
}
</script>
