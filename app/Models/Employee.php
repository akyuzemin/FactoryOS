<?php

class Employee
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Tüm çalışanları departman, pozisyon, vardiya ve kullanıcı bilgileriyle birlikte döner.
     */
    public function getAllWithDetails(array $filters = []): array
    {
        $sql = "
            SELECT 
                e.id,
                e.user_id,
                e.registration_no,
                e.first_name,
                e.last_name,
                e.department_id,
                e.position_id,
                e.phone,
                e.email,
                e.employment_type,
                e.status,
                e.hire_date,
                e.termination_date,
                e.default_shift_id,
                e.is_active,
                e.created_at,
                e.updated_at,
                d.code AS department_code,
                d.name AS department_name,
                p.title AS position_title,
                p.level AS position_level,
                s.code AS shift_code,
                s.name AS shift_name,
                u.username AS linked_username
            FROM employees e
            INNER JOIN departments d ON e.department_id = d.id
            INNER JOIN positions p ON e.position_id = p.id
            LEFT JOIN energy_shifts s ON e.default_shift_id = s.id
            LEFT JOIN users u ON e.user_id = u.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (
                e.registration_no LIKE :search
                OR e.first_name LIKE :search
                OR e.last_name LIKE :search
                OR CONCAT(e.first_name, ' ', e.last_name) LIKE :search
                OR e.email LIKE :search
                OR e.phone LIKE :search
                OR d.name LIKE :search
                OR p.title LIKE :search
            )";
            $params['search'] = '%' . trim($filters['search']) . '%';
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND e.department_id = :department_id";
            $params['department_id'] = (int)$filters['department_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND e.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['employment_type'])) {
            $sql .= " AND e.employment_type = :employment_type";
            $params['employment_type'] = $filters['employment_type'];
        }

        if (!empty($filters['shift_id'])) {
            if ($filters['shift_id'] === 'none') {
                $sql .= " AND e.default_shift_id IS NULL";
            } else {
                $sql .= " AND e.default_shift_id = :shift_id";
                $params['shift_id'] = (int)$filters['shift_id'];
            }
        }

        $sql .= " ORDER BY e.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Çalışanlar KPI özet istatistiklerini döner.
     */
    public function getKpiSummary(): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN status = 'ON_LEAVE' THEN 1 ELSE 0 END) AS on_leave_count,
                SUM(CASE WHEN status = 'SUSPENDED' THEN 1 ELSE 0 END) AS suspended_count,
                SUM(CASE WHEN status = 'TERMINATED' THEN 1 ELSE 0 END) AS terminated_count
            FROM employees
        ";

        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

        return [
            'total'      => (int)($row['total_count'] ?? 0),
            'active'     => (int)($row['active_count'] ?? 0),
            'on_leave'   => (int)($row['on_leave_count'] ?? 0),
            'suspended'  => (int)($row['suspended_count'] ?? 0),
            'terminated' => (int)($row['terminated_count'] ?? 0),
        ];
    }

    /**
     * Filtreleme için departman, pozisyon ve vardiya listelerini döner.
     */
    public function getFilterOptions(): array
    {
        $departments = $this->pdo->query("SELECT id, code, name FROM departments WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $shifts = $this->pdo->query("SELECT id, code, name FROM energy_shifts WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

        return [
            'departments' => $departments,
            'shifts'      => $shifts,
            'statuses'    => [
                'ACTIVE'     => 'Aktif',
                'ON_LEAVE'   => 'İzinli',
                'SUSPENDED'  => 'Askıda',
                'TERMINATED' => 'İşten Ayrılan'
            ],
            'employment_types' => [
                'FULL_TIME'  => 'Tam Zamanlı',
                'PART_TIME'  => 'Yarı Zamanlı',
                'CONTRACTOR' => 'Yüklenici / Taşeron',
                'INTERN'     => 'Stajyer'
            ]
        ];
    }

    /**
     * Tek bir çalışanın tüm detaylarını ilişkili tablolarla (departman, pozisyon, vardiya, kullanıcı, rol) döner.
     */
    public function findByIdWithDetails(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $sql = "
            SELECT 
                e.id,
                e.user_id,
                e.registration_no,
                e.first_name,
                e.last_name,
                e.department_id,
                e.position_id,
                e.phone,
                e.email,
                e.employment_type,
                e.status,
                e.hire_date,
                e.termination_date,
                e.default_shift_id,
                e.is_active,
                e.created_at,
                e.updated_at,
                d.code AS department_code,
                d.name AS department_name,
                d.description AS department_description,
                p.title AS position_title,
                p.level AS position_level,
                s.code AS shift_code,
                s.name AS shift_name,
                s.start_time AS shift_start_time,
                s.end_time AS shift_end_time,
                u.username AS linked_username,
                u.email AS user_email,
                r.name AS user_role_name
            FROM employees e
            INNER JOIN departments d ON e.department_id = d.id
            INNER JOIN positions p ON e.position_id = p.id
            LEFT JOIN energy_shifts s ON e.default_shift_id = s.id
            LEFT JOIN users u ON e.user_id = u.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE e.id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Mevcut yıl için sıradaki benzersiz personel sicil numarasını otomatik üretir (ör. PER-2026-0101).
     */
    public function generateNextRegistrationNo(): string
    {
        $year = date('Y');
        $prefix = "PER-{$year}-";

        $sql = "SELECT MAX(CAST(SUBSTRING(registration_no, 10) AS UNSIGNED)) AS max_seq 
                FROM employees 
                WHERE registration_no LIKE :prefix";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':prefix' => $prefix . '%']);
        $maxSeq = (int)$stmt->fetchColumn();

        $nextSeq = $maxSeq + 1;
        return sprintf('%s%04d', $prefix, $nextSeq);
    }

    /**
     * Form oluşturma/düzenleme için gereken ilişkili veri seçeneklerini döner.
     * $currentUserId verilirse, o kullanıcı da seçenekler arasına dahil edilir.
     */
    public function getFormDataOptions(?int $currentUserId = null): array
    {
        $departments = $this->pdo->query("SELECT id, code, name FROM departments WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $positions = $this->pdo->query("SELECT id, department_id, title, level FROM positions WHERE is_active = 1 ORDER BY department_id, title ASC")->fetchAll(PDO::FETCH_ASSOC);
        $shifts = $this->pdo->query("SELECT id, code, name, start_time, end_time FROM energy_shifts WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Kullanılabilir kullanıcılar (aktif ve henüz hiçbir çalışana bağlı olmayanlar + düzenlenmekte olan çalışanın kendi kullanıcısı)
        $sqlUsers = "
            SELECT u.id, u.username, u.first_name, u.last_name, r.name AS role_name
            FROM users u
            LEFT JOIN employees e ON u.id = e.user_id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.is_active = 1 AND (e.id IS NULL " . ($currentUserId ? "OR u.id = :cur_uid" : "") . ")
            ORDER BY u.username ASC
        ";
        $stmtUser = $this->pdo->prepare($sqlUsers);
        if ($currentUserId) {
            $stmtUser->execute([':cur_uid' => $currentUserId]);
        } else {
            $stmtUser->execute();
        }
        $availableUsers = $stmtUser->fetchAll(PDO::FETCH_ASSOC);

        return [
            'departments'      => $departments,
            'positions'        => $positions,
            'shifts'           => $shifts,
            'available_users'  => $availableUsers,
            'statuses'         => [
                'ACTIVE'     => 'Aktif',
                'ON_LEAVE'   => 'İzinli',
                'SUSPENDED'  => 'Askıda',
                'TERMINATED' => 'İşten Ayrılan'
            ],
            'employment_types' => [
                'FULL_TIME'  => 'Tam Zamanlı',
                'PART_TIME'  => 'Yarı Zamanlı',
                'CONTRACTOR' => 'Yüklenici / Taşeron',
                'INTERN'     => 'Stajyer'
            ]
        ];
    }

    /**
     * Formdan gelen çalışan verilerini doğrular.
     * $currentEmployeeId verilirse, user_id benzersizlik kontrolünde çalışanın kendisi hariç tutulur.
     */
    public function validateEmployeeData(array $data, ?int $currentEmployeeId = null): ?string
    {
        $firstName = trim((string)($data['first_name'] ?? ''));
        $lastName = trim((string)($data['last_name'] ?? ''));
        $departmentId = (int)($data['department_id'] ?? 0);
        $positionId = (int)($data['position_id'] ?? 0);
        $employmentType = (string)($data['employment_type'] ?? '');
        $status = (string)($data['status'] ?? '');
        $hireDate = trim((string)($data['hire_date'] ?? ''));
        $terminationDate = trim((string)($data['termination_date'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $defaultShiftId = !empty($data['default_shift_id']) ? (int)$data['default_shift_id'] : null;
        $userId = !empty($data['user_id']) ? (int)$data['user_id'] : null;

        if ($firstName === '') {
            return 'Çalışan adı zorunludur.';
        }
        if (mb_strlen($firstName) > 100) {
            return 'Çalışan adı en fazla 100 karakter olabilir.';
        }

        if ($lastName === '') {
            return 'Çalışan soyadı zorunludur.';
        }
        if (mb_strlen($lastName) > 100) {
            return 'Çalışan soyadı en fazla 100 karakter olabilir.';
        }

        if ($departmentId <= 0) {
            return 'Geçerli bir departman seçiniz.';
        }

        // Departman geçerlilik kontrolü
        $deptStmt = $this->pdo->prepare("SELECT 1 FROM departments WHERE id = :id AND is_active = 1");
        $deptStmt->execute([':id' => $departmentId]);
        if (!$deptStmt->fetchColumn()) {
            return 'Seçilen departman bulunamadı veya pasif durumda.';
        }

        if ($positionId <= 0) {
            return 'Geçerli bir pozisyon seçiniz.';
        }

        // Pozisyonun seçilen departmana ait olduğunu doğrula
        $posStmt = $this->pdo->prepare("SELECT 1 FROM positions WHERE id = :position_id AND department_id = :department_id AND is_active = 1");
        $posStmt->execute([':position_id' => $positionId, ':department_id' => $departmentId]);
        if (!$posStmt->fetchColumn()) {
            return 'Seçilen pozisyon bu departmana ait değildir.';
        }

        $validEmploymentTypes = ['FULL_TIME', 'PART_TIME', 'CONTRACTOR', 'INTERN'];
        if (!in_array($employmentType, $validEmploymentTypes, true)) {
            return 'Geçersiz istihdam türü seçildi.';
        }

        $validStatuses = ['ACTIVE', 'ON_LEAVE', 'SUSPENDED', 'TERMINATED'];
        if (!in_array($status, $validStatuses, true)) {
            return 'Geçersiz çalışma durumu seçildi.';
        }

        if ($hireDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireDate)) {
            return 'Geçerli bir işe giriş tarihi (YYYY-AA-GG) giriniz.';
        }

        if ($status === 'TERMINATED') {
            if ($terminationDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $terminationDate)) {
                return 'İşten ayrılan çalışanlar için işten ayrılış tarihi zorunludur.';
            }
            if ($terminationDate < $hireDate) {
                return 'İşten ayrılış tarihi işe giriş tarihinden önce olamaz.';
            }
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Geçerli bir e-posta adresi giriniz.';
        }
        if (mb_strlen($email) > 150) {
            return 'E-posta adresi en fazla 150 karakter olabilir.';
        }

        if (mb_strlen($phone) > 30) {
            return 'Telefon numarası en fazla 30 karakter olabilir.';
        }

        if ($defaultShiftId !== null) {
            $shiftStmt = $this->pdo->prepare("SELECT 1 FROM energy_shifts WHERE id = :id AND is_active = 1");
            $shiftStmt->execute([':id' => $defaultShiftId]);
            if (!$shiftStmt->fetchColumn()) {
                return 'Seçilen vardiya bulunamadı veya pasif durumda.';
            }
        }

        if ($userId !== null) {
            $userStmt = $this->pdo->prepare("SELECT 1 FROM users WHERE id = :id AND is_active = 1");
            $userStmt->execute([':id' => $userId]);
            if (!$userStmt->fetchColumn()) {
                return 'Seçilen kullanıcı hesabı bulunamadı veya pasif durumda.';
            }

            // user_id başka çalışana bağlı mı? (kendisi hariç)
            $sqlCheck = "SELECT 1 FROM employees WHERE user_id = :user_id" . ($currentEmployeeId ? " AND id != :cur_emp_id" : "");
            $userCheck = $this->pdo->prepare($sqlCheck);
            $paramsCheck = [':user_id' => $userId];
            if ($currentEmployeeId) {
                $paramsCheck[':cur_emp_id'] = $currentEmployeeId;
            }
            $userCheck->execute($paramsCheck);
            if ($userCheck->fetchColumn()) {
                return 'Seçilen kullanıcı hesabı zaten başka bir çalışana atanmış.';
            }
        }

        return null;
    }

    /**
     * Yeni bir çalışan kaydı oluşturur ve eklenen kaydın ID'sini döner.
     */
    public function create(array $data): int
    {
        $regNo = $this->generateNextRegistrationNo();
        $status = $data['status'];
        $isActive = ($status === 'TERMINATED') ? 0 : 1;
        $terminationDate = ($status === 'TERMINATED') ? (!empty($data['termination_date']) ? $data['termination_date'] : null) : null;
        $defaultShiftId = !empty($data['default_shift_id']) ? (int)$data['default_shift_id'] : null;
        $userId = !empty($data['user_id']) ? (int)$data['user_id'] : null;
        $phone = !empty($data['phone']) ? trim((string)$data['phone']) : null;
        $email = !empty($data['email']) ? trim((string)$data['email']) : null;

        $sql = "
            INSERT INTO employees (
                user_id,
                registration_no,
                first_name,
                last_name,
                department_id,
                position_id,
                phone,
                email,
                employment_type,
                status,
                hire_date,
                termination_date,
                default_shift_id,
                is_active,
                created_at,
                updated_at
            ) VALUES (
                :user_id,
                :registration_no,
                :first_name,
                :last_name,
                :department_id,
                :position_id,
                :phone,
                :email,
                :employment_type,
                :status,
                :hire_date,
                :termination_date,
                :default_shift_id,
                :is_active,
                NOW(),
                NOW()
            )
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id'          => $userId,
            ':registration_no'  => $regNo,
            ':first_name'       => trim((string)$data['first_name']),
            ':last_name'        => trim((string)$data['last_name']),
            ':department_id'    => (int)$data['department_id'],
            ':position_id'      => (int)$data['position_id'],
            ':phone'            => $phone,
            ':email'            => $email,
            ':employment_type'  => $data['employment_type'],
            ':status'           => $status,
            ':hire_date'        => $data['hire_date'],
            ':termination_date' => $terminationDate,
            ':default_shift_id' => $defaultShiftId,
            ':is_active'        => $isActive,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Mevcut bir çalışanın bilgilerini günceller. Sicil numarası kesinlikle değiştirilmez.
     */
    public function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            return false;
        }

        $status = $data['status'];
        $isActive = ($status === 'TERMINATED') ? 0 : 1;
        $terminationDate = ($status === 'TERMINATED') ? (!empty($data['termination_date']) ? $data['termination_date'] : null) : null;
        $defaultShiftId = !empty($data['default_shift_id']) ? (int)$data['default_shift_id'] : null;
        $userId = !empty($data['user_id']) ? (int)$data['user_id'] : null;
        $phone = !empty($data['phone']) ? trim((string)$data['phone']) : null;
        $email = !empty($data['email']) ? trim((string)$data['email']) : null;

        $sql = "
            UPDATE employees SET
                user_id          = :user_id,
                first_name       = :first_name,
                last_name        = :last_name,
                department_id    = :department_id,
                position_id      = :position_id,
                phone            = :phone,
                email            = :email,
                employment_type  = :employment_type,
                status           = :status,
                hire_date        = :hire_date,
                termination_date = :termination_date,
                default_shift_id = :default_shift_id,
                is_active        = :is_active,
                updated_at       = NOW()
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'               => $id,
            ':user_id'          => $userId,
            ':first_name'       => trim((string)$data['first_name']),
            ':last_name'        => trim((string)$data['last_name']),
            ':department_id'    => (int)$data['department_id'],
            ':position_id'      => (int)$data['position_id'],
            ':phone'            => $phone,
            ':email'            => $email,
            ':employment_type'  => $data['employment_type'],
            ':status'           => $status,
            ':hire_date'        => $data['hire_date'],
            ':termination_date' => $terminationDate,
            ':default_shift_id' => $defaultShiftId,
            ':is_active'        => $isActive,
        ]);
    }

    /**
     * Çalışanın atanmış vardiya geçmişini ve gelecek vardiyalarını döner (en fazla $limit kayıt).
     */
    public function getShiftsByEmployee(int $employeeId, int $limit = 30): array
    {
        if ($employeeId <= 0) {
            return [];
        }

        $limit = max(1, min(100, $limit));

        $sql = "
            SELECT 
                es.id,
                es.employee_id,
                es.shift_id,
                es.assigned_date,
                es.notes,
                es.created_at,
                s.code AS shift_code,
                s.name AS shift_name,
                s.start_time AS shift_start_time,
                s.end_time AS shift_end_time
            FROM employee_shifts es
            INNER JOIN energy_shifts s ON es.shift_id = s.id
            WHERE es.employee_id = :employee_id
            ORDER BY es.assigned_date DESC, es.id DESC
            LIMIT {$limit}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':employee_id' => $employeeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aktif enerji vardiyalarını döner.
     */
    public function getActiveShifts(): array
    {
        $stmt = $this->pdo->query("SELECT id, code, name, start_time, end_time FROM energy_shifts WHERE is_active = 1 ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vardiya atama verilerini doğrular.
     */
    public function validateShiftAssignment(int $employeeId, int $shiftId, string $date, ?string $notes = null): ?string
    {
        if ($employeeId <= 0) {
            return 'Geçersiz çalışan ID.';
        }

        // Çalışanın mevcut durumunu ve tarihlerini getir
        $empStmt = $this->pdo->prepare("SELECT id, status, hire_date, termination_date FROM employees WHERE id = :id LIMIT 1");
        $empStmt->execute([':id' => $employeeId]);
        $emp = $empStmt->fetch(PDO::FETCH_ASSOC);

        if (!$emp) {
            return 'Çalışan kaydı bulunamadı.';
        }

        if ($emp['status'] === 'TERMINATED') {
            return 'İşten ayrılmış çalışanlara vardiya atanamaz.';
        }

        $date = trim($date);
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return 'Geçerli bir vardiya tarihi (YYYY-AA-GG) giriniz.';
        }

        if ($date < $emp['hire_date']) {
            return "Vardiya tarihi, çalışanın işe giriş tarihinden ({$emp['hire_date']}) önce olamaz.";
        }

        if (!empty($emp['termination_date']) && $date > $emp['termination_date']) {
            return "Vardiya tarihi, çalışanın işten ayrılış tarihinden ({$emp['termination_date']}) sonra olamaz.";
        }

        if ($shiftId <= 0) {
            return 'Geçerli bir vardiya seçiniz.';
        }

        $shiftStmt = $this->pdo->prepare("SELECT 1 FROM energy_shifts WHERE id = :id AND is_active = 1 LIMIT 1");
        $shiftStmt->execute([':id' => $shiftId]);
        if (!$shiftStmt->fetchColumn()) {
            return 'Seçilen vardiya bulunamadı veya pasif durumda.';
        }

        if ($notes !== null && mb_strlen($notes) > 255) {
            return 'Not alanı en fazla 255 karakter olabilir.';
        }

        return null;
    }

    /**
     * Çalışana belirli bir gün için vardiya atar. Aynı gün için zaten kayıt varsa günceller.
     */
    public function assignShift(int $employeeId, int $shiftId, string $date, ?string $notes = null): bool
    {
        $notes = !empty($notes) ? trim($notes) : null;

        $sql = "
            INSERT INTO employee_shifts (
                employee_id,
                shift_id,
                assigned_date,
                notes,
                created_at
            ) VALUES (
                :employee_id,
                :shift_id,
                :assigned_date,
                :notes,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                shift_id = VALUES(shift_id),
                notes = VALUES(notes)
        ";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':employee_id'   => $employeeId,
            ':shift_id'      => $shiftId,
            ':assigned_date' => $date,
            ':notes'         => $notes,
        ]);
    }

    /**
     * Belirli bir vardiya atamasını siler (çalışan ID doğrulaması ile).
     */
    public function deleteShift(int $shiftAssignmentId, int $employeeId): bool
    {
        if ($shiftAssignmentId <= 0 || $employeeId <= 0) {
            return false;
        }

        $sql = "DELETE FROM employee_shifts WHERE id = :id AND employee_id = :employee_id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'          => $shiftAssignmentId,
            ':employee_id' => $employeeId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Kullanıcı hesabına zimmetli/sorumlu olduğu aktif envanter varlıklarını döner.
     */
    public function getAssignedAssetsByUserId(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $sql = "
            SELECT 
                a.id,
                a.asset_code,
                a.asset_name,
                a.serial_no,
                a.model_no,
                a.status,
                a.purchase_date,
                c.name AS category_name,
                w.name AS warehouse_name,
                l.name AS location_name,
                pl.name AS production_line_name
            FROM inventory_assets a
            LEFT JOIN inventory_categories c ON a.inventory_category_id = c.id
            LEFT JOIN warehouses w ON a.warehouse_id = w.id
            LEFT JOIN locations l ON a.location_id = l.id
            LEFT JOIN production_lines pl ON a.production_line_id = pl.id
            WHERE a.responsible_user_id = :user_id 
              AND a.is_active = 1
            ORDER BY a.status ASC, a.asset_name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
