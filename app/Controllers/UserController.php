<?php

require_once __DIR__ . '/../Models/User.php';

class UserController
{
    private User $user;

    public function __construct(PDO $pdo)
    {
        $this->user = new User($pdo);
    }

    public function index(): void
    {
        $users = $this->user->getAllWithRoles();

        require __DIR__ . '/../../views/users/index.php';
    }

    public function create(): void
    {
        $formData = $this->emptyFormData();
        $error = null;
        $roles = $this->user->getRoles();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = $this->readFormData();
            $error = $this->validateFormData($formData, true);

            if ($error === null) {
                try {
                    $this->user->create($this->databaseData($formData));
                    header('Location: /stok-takip/public/users');
                    exit;
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                } catch (PDOException $exception) {
                    $error = $this->databaseError($exception);
                }
            }
        }

        $formTitle = 'Yeni Kullanıcı';
        $formAction = '/stok-takip/public/users/create';

        require __DIR__ . '/../../views/users/form.php';
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $user = $this->user->findByIdForManagement($id);

        if ($user === null) {
            http_response_code(404);
            echo '404 - Kullanıcı bulunamadı.';
            return;
        }

        $formData = $user;
        unset($formData['password_hash']);
        $formData['password'] = '';
        $error = null;
        $roles = $this->user->getRoles();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = array_merge($formData, $this->readFormData());
            $error = $this->validateFormData($formData, false);

            if ($error === null) {
                try {
                    $passwordHash = $formData['password'] === ''
                        ? null
                        : password_hash($formData['password'], PASSWORD_DEFAULT);
                    $this->user->update(
                        $id,
                        $this->databaseData($formData),
                        $passwordHash,
                        (int) ($_SESSION['user_id'] ?? 0)
                    );
                    header('Location: /stok-takip/public/users');
                    exit;
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                } catch (PDOException $exception) {
                    $error = $this->databaseError($exception);
                }
            }
        }

        $formTitle = 'Kullanıcı Düzenle';
        $formAction = '/stok-takip/public/users/edit?id=' . $id;

        require __DIR__ . '/../../views/users/form.php';
    }

    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo '405 - Geçersiz istek.';
            return;
        }

        try {
            $this->user->deactivate(
                (int) ($_POST['id'] ?? 0),
                (int) ($_SESSION['user_id'] ?? 0)
            );
        } catch (InvalidArgumentException $exception) {
            http_response_code(403);
            echo htmlspecialchars($exception->getMessage());
            return;
        }

        header('Location: /stok-takip/public/users');
        exit;
    }

    private function emptyFormData(): array
    {
        return [
            'username' => '',
            'email' => '',
            'password' => '',
            'first_name' => '',
            'last_name' => '',
            'role_id' => '',
            'is_active' => '1',
        ];
    }

    private function readFormData(): array
    {
        return [
            'username' => trim((string) ($_POST['username'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'role_id' => trim((string) ($_POST['role_id'] ?? '')),
            'is_active' => ($_POST['is_active'] ?? '0') === '1' ? '1' : '0',
        ];
    }

    private function validateFormData(array $data, bool $passwordRequired): ?string
    {
        if ($data['username'] === '' || $data['email'] === '' || $data['first_name'] === '' || $data['last_name'] === '') {
            return 'Kullanıcı adı, e-posta, ad ve soyad zorunludur.';
        }

        if (strlen($data['username']) > 50 || strlen($data['email']) > 150 || strlen($data['first_name']) > 100 || strlen($data['last_name']) > 100) {
            return 'Kullanıcı bilgilerinden biri izin verilen uzunluğu aşıyor.';
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Geçerli bir e-posta adresi girin.';
        }

        if ($passwordRequired && strlen($data['password']) < 8) {
            return 'Şifre en az 8 karakter olmalıdır.';
        }

        if (!$passwordRequired && $data['password'] !== '' && strlen($data['password']) < 8) {
            return 'Yeni şifre en az 8 karakter olmalıdır.';
        }

        if (!ctype_digit($data['role_id']) || (int) $data['role_id'] < 1) {
            return 'Geçerli bir rol seçin.';
        }

        if (!in_array($data['is_active'], ['0', '1'], true)) {
            return 'Geçerli bir kullanıcı durumu seçin.';
        }

        return null;
    }

    private function databaseData(array $data): array
    {
        return [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role_id' => (int) $data['role_id'],
            'is_active' => (int) $data['is_active'],
        ];
    }

    private function databaseError(PDOException $exception): string
    {
        if ($exception->getCode() === '23000') {
            return 'Bu kullanıcı adı veya e-posta zaten kullanılıyor.';
        }

        return 'Kullanıcı kaydedilirken bir hata oluştu.';
    }
}
