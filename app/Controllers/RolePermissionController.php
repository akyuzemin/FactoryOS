<?php

require_once __DIR__ . '/../Models/RolePermission.php';

class RolePermissionController
{
    private RolePermission $model;

    public function __construct(PDO $pdo)
    {
        $this->model = new RolePermission($pdo);
    }

    /**
     * Rol ve yetki yönetim ekranını listeler.
     */
    public function index(): void
    {
        $roles = $this->model->getRoles();
        
        $selectedRoleId = isset($_GET['role_id']) && ctype_digit((string)$_GET['role_id']) 
            ? (int)$_GET['role_id'] 
            : ($roles[0]['id'] ?? 1);

        // Seçilen rolün varlığını doğrula
        $roleFound = false;
        foreach ($roles as $r) {
            if ((int)$r['id'] === $selectedRoleId) {
                $roleFound = true;
                break;
            }
        }
        if (!$roleFound && !empty($roles)) {
            $selectedRoleId = (int)$roles[0]['id'];
        }

        $currentRole = $this->model->getRoleById($selectedRoleId);
        $groupedPermissions = $this->model->getPermissionsGrouped();
        $assignedPermissionIds = $this->model->getRolePermissionIds($selectedRoleId);

        // Flash mesajları oku ve temizle
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $pageTitle = 'Rol &amp; Yetki Yönetimi';

        require __DIR__ . '/../../views/role-permissions/index.php';
    }

    /**
     * Seçilen rolün izinlerini günceller.
     */
    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo '405 - Geçersiz istek metodu.';
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $roleId = (int)($_POST['role_id'] ?? 0);
        $permissionIds = isset($_POST['permissions']) && is_array($_POST['permissions']) 
            ? $_POST['permissions'] 
            : [];

        $adminUserId = (int)($_SESSION['user_id'] ?? 0);

        try {
            $this->model->updateRolePermissions($roleId, $permissionIds, $adminUserId);
            $role = $this->model->getRoleById($roleId);
            $roleName = $role['name'] ?? "Rol #$roleId";
            $_SESSION['flash_success'] = "<strong>{$roleName}</strong> rolünün erişim yetkileri başarıyla güncellendi.";
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Yetki güncelleme sırasında bir hata oluştu: ' . $e->getMessage();
        }

        if (!headers_sent()) {
            header('Location: /stok-takip/public/role-permissions?role_id=' . $roleId);
        }
        exit;
    }

    /**
     * Seçilen rolün yetkilerini varsayılan ayarlara döndürür.
     */
    public function reset(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo '405 - Geçersiz istek metodu.';
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $roleId = (int)($_POST['role_id'] ?? 1);
        $adminUserId = (int)($_SESSION['user_id'] ?? 0);

        try {
            $this->model->resetRolePermissions($roleId, $adminUserId);
            $role = $this->model->getRoleById($roleId);
            $roleName = $role['name'] ?? "Rol #$roleId";
            $_SESSION['flash_success'] = "<strong>{$roleName}</strong> rolünün yetkileri varsayılan ayarlarına başarıyla döndürüldü.";
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'Varsayılan yetkilere dönerken bir hata oluştu: ' . $e->getMessage();
        }

        if (!headers_sent()) {
            header('Location: /stok-takip/public/role-permissions?role_id=' . $roleId);
        }
        exit;
    }
}
