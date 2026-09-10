<?php

require_once __DIR__ . '/../Models/Employee.php';

class EmployeeController
{
    private Employee $employeeModel;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->employeeModel = new Employee($pdo);
    }

    /**
     * GET /employees
     * Çalışanlar listesi ve KPI gösterge ekranı.
     */
    public function index(): void
    {
        $filters = [
            'search'          => trim((string)($_GET['search'] ?? '')),
            'department_id'   => (int)($_GET['department_id'] ?? 0),
            'status'          => trim((string)($_GET['status'] ?? '')),
            'employment_type' => trim((string)($_GET['employment_type'] ?? '')),
            'shift_id'        => trim((string)($_GET['shift_id'] ?? '')),
        ];

        $employees = $this->employeeModel->getAllWithDetails($filters);
        $kpi = $this->employeeModel->getKpiSummary();
        $filterOptions = $this->employeeModel->getFilterOptions();

        require __DIR__ . '/../../views/employees/index.php';
    }

    /**
     * GET /employees/show?id={id}
     * Salt okunur çalışan detay kartı ekranı.
     */
    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(404);
            echo "404 - Geçersiz çalışan ID'si.";
            return;
        }

        $employee = $this->employeeModel->findByIdWithDetails($id);

        if ($employee === null) {
            http_response_code(404);
            echo "404 - Çalışan bulunamadı.";
            return;
        }

        $employeeShifts = $this->employeeModel->getShiftsByEmployee($id, 30);
        $availableShifts = $this->employeeModel->getActiveShifts();

        $assignedAssets = [];
        if (!empty($employee['user_id'])) {
            $permissionService = new PermissionService($this->pdo);
            $canViewInventory = isset($_SESSION['role_id']) && $permissionService->hasPermission((int)$_SESSION['role_id'], 'inventory.view');
            if ($canViewInventory) {
                $assignedAssets = $this->employeeModel->getAssignedAssetsByUserId((int)$employee['user_id']);
            }
        }

        require __DIR__ . '/../../views/employees/show.php';
    }

    /**
     * POST /employees/shifts/assign
     * Çalışana belirli bir gün için vardiya atar veya günceller.
     */
    public function assignShift(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /stok-takip/public/employees');
            exit;
        }

        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $assignedDate = trim((string)($_POST['assigned_date'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($employeeId <= 0) {
            $_SESSION['error'] = 'Geçersiz çalışan ID.';
            header('Location: /stok-takip/public/employees');
            exit;
        }

        $error = $this->employeeModel->validateShiftAssignment($employeeId, $shiftId, $assignedDate, $notes);

        if ($error !== null) {
            $_SESSION['error'] = $error;
        } else {
            try {
                $this->employeeModel->assignShift($employeeId, $shiftId, $assignedDate, $notes !== '' ? $notes : null);
                $_SESSION['success'] = 'Vardiya ataması başarıyla kaydedildi.';
            } catch (Throwable $e) {
                $_SESSION['error'] = 'Vardiya atanırken beklenmeyen bir veritabanı hatası oluştu.';
            }
        }

        header('Location: /stok-takip/public/employees/show?id=' . $employeeId);
        exit;
    }

    /**
     * POST /employees/shifts/delete
     * Çalışana ait vardiya atamasını siler.
     */
    public function deleteShift(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /stok-takip/public/employees');
            exit;
        }

        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $shiftAssignmentId = (int)($_POST['id'] ?? 0);

        if ($employeeId <= 0 || $shiftAssignmentId <= 0) {
            $_SESSION['error'] = 'Geçersiz işlem parametreleri.';
            header('Location: /stok-takip/public/employees');
            exit;
        }

        try {
            $deleted = $this->employeeModel->deleteShift($shiftAssignmentId, $employeeId);
            if ($deleted) {
                $_SESSION['success'] = 'Vardiya kaydı başarıyla silindi.';
            } else {
                $_SESSION['error'] = 'Silinecek vardiya kaydı bulunamadı veya bu çalışana ait değil.';
            }
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Vardiya kaydı silinirken hata oluştu.';
        }

        header('Location: /stok-takip/public/employees/show?id=' . $employeeId);
        exit;
    }

    /**
     * GET / POST /employees/create
     * Yeni çalışan oluşturma formu ve kaydı.
     */
    public function create(): void
    {
        $options = $this->employeeModel->getFormDataOptions();
        $predictedRegNo = $this->employeeModel->generateNextRegistrationNo();
        $error = null;
        $formData = $this->emptyFormData();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = $_POST;
            $error = $this->employeeModel->validateEmployeeData($_POST);

            if ($error === null) {
                try {
                    $newId = $this->employeeModel->create($_POST);
                    $_SESSION['success'] = 'Yeni çalışan başarıyla kaydedildi.';
                    header('Location: /stok-takip/public/employees/show?id=' . $newId);
                    exit;
                } catch (Throwable $e) {
                    $error = 'Çalışan kaydedilirken beklenmeyen bir veritabanı hatası oluştu.';
                }
            }
        }

        $formTitle = 'Yeni Çalışan Ekle';
        $formAction = '/stok-takip/public/employees/create';

        require __DIR__ . '/../../views/employees/create.php';
    }

    /**
     * GET / POST /employees/edit?id={id}
     * Mevcut çalışan düzenleme formu ve güncelleme işlemi.
     */
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(404);
            echo "404 - Geçersiz çalışan ID'si.";
            return;
        }

        $employee = $this->employeeModel->findByIdWithDetails($id);

        if ($employee === null) {
            http_response_code(404);
            echo "404 - Çalışan bulunamadı.";
            return;
        }

        $options = $this->employeeModel->getFormDataOptions(!empty($employee['user_id']) ? (int)$employee['user_id'] : null);
        $error = null;
        $formData = $employee;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData = array_merge($employee, $_POST);
            $error = $this->employeeModel->validateEmployeeData($_POST, $id);

            if ($error === null) {
                try {
                    $this->employeeModel->update($id, $_POST);
                    $_SESSION['success'] = 'Çalışan bilgileri başarıyla güncellendi.';
                    header('Location: /stok-takip/public/employees/show?id=' . $id);
                    exit;
                } catch (Throwable $e) {
                    $error = 'Çalışan bilgileri güncellenirken beklenmeyen bir veritabanı hatası oluştu.';
                }
            }
        }

        $formTitle = 'Çalışan Düzenle: ' . ($employee['first_name'] . ' ' . $employee['last_name']);
        $formAction = '/stok-takip/public/employees/edit?id=' . $id;

        require __DIR__ . '/../../views/employees/edit.php';
    }

    private function emptyFormData(): array
    {
        return [
            'first_name'         => '',
            'last_name'          => '',
            'department_id'      => '',
            'position_id'        => '',
            'phone'              => '',
            'email'              => '',
            'employment_type'    => 'FULL_TIME',
            'status'             => 'ACTIVE',
            'hire_date'          => date('Y-m-d'),
            'termination_date'   => '',
            'default_shift_id'   => '',
            'user_id'            => '',
        ];
    }
}
