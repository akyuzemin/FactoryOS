<?php
declare(strict_types=1);

class EmployeeDashboard
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Çalışan KPI özetini tek bir aggregate sorguyla döner.
     */
    public function getEmployeeKpis(): array
    {
        $sql = "
            SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'ON_LEAVE' THEN 1 ELSE 0 END) AS on_leave,
                SUM(CASE WHEN status = 'SUSPENDED' THEN 1 ELSE 0 END) AS suspended,
                SUM(CASE WHEN status = 'TERMINATED' THEN 1 ELSE 0 END) AS `terminated`
            FROM employees
        ";

        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total'      => (int) ($row['total'] ?? 0),
            'active'     => (int) ($row['active'] ?? 0),
            'on_leave'   => (int) ($row['on_leave'] ?? 0),
            'suspended'  => (int) ($row['suspended'] ?? 0),
            'terminated' => (int) ($row['terminated'] ?? 0),
        ];
    }

    /**
     * Belirtilen gün için efektif vardiya dağılımını döner (Günlük atama öncelikli, varsayılan fallback).
     */
    public function getTodayShiftDistribution(string $today): array
    {
        $sql = "
            SELECT 
                COALESCE(s_daily.code, s_def.code, 'NO_SHIFT') AS shift_code,
                COALESCE(s_daily.name, s_def.name, 'Vardiyasız') AS shift_name,
                COALESCE(s_daily.start_time, s_def.start_time) AS start_time,
                COALESCE(s_daily.end_time, s_def.end_time) AS end_time,
                COUNT(e.id) AS total_count,
                SUM(CASE WHEN e.status = 'ACTIVE' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN es.shift_id IS NOT NULL THEN 1 ELSE 0 END) AS daily_assigned_count,
                SUM(CASE WHEN es.shift_id IS NULL AND e.default_shift_id IS NOT NULL THEN 1 ELSE 0 END) AS fallback_count
            FROM employees e
            LEFT JOIN employee_shifts es ON es.employee_id = e.id AND es.assigned_date = :today_date
            LEFT JOIN energy_shifts s_daily ON s_daily.id = es.shift_id
            LEFT JOIN energy_shifts s_def ON s_def.id = e.default_shift_id
            GROUP BY shift_code, shift_name, start_time, end_time
            ORDER BY (CASE WHEN shift_code = 'NO_SHIFT' THEN 99 ELSE 1 END), shift_code ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':today_date' => $today]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Departman bazında personel durumu dağılımını döner.
     */
    public function getDepartmentDistribution(): array
    {
        $sql = "
            SELECT 
                d.id,
                d.code,
                d.name,
                COUNT(e.id) AS total_employees,
                SUM(CASE WHEN e.status = 'ACTIVE' THEN 1 ELSE 0 END) AS active_employees,
                SUM(CASE WHEN e.status = 'ON_LEAVE' THEN 1 ELSE 0 END) AS on_leave_employees,
                SUM(CASE WHEN e.status = 'SUSPENDED' THEN 1 ELSE 0 END) AS suspended_employees,
                SUM(CASE WHEN e.status = 'TERMINATED' THEN 1 ELSE 0 END) AS terminated_employees
            FROM departments d
            LEFT JOIN employees e ON e.department_id = d.id
            GROUP BY d.id, d.code, d.name
            ORDER BY total_employees DESC, d.name ASC
        ";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Departman x Bugünkü Efektif Vardiya matrisini döner.
     */
    public function getDepartmentShiftMatrix(string $today): array
    {
        $sql = "
            SELECT 
                d.id AS department_id,
                d.code AS department_code,
                d.name AS department_name,
                SUM(CASE WHEN COALESCE(es.shift_id, e.default_shift_id) = 1 THEN 1 ELSE 0 END) AS shift_1_count,
                SUM(CASE WHEN COALESCE(es.shift_id, e.default_shift_id) = 2 THEN 1 ELSE 0 END) AS shift_2_count,
                SUM(CASE WHEN COALESCE(es.shift_id, e.default_shift_id) = 3 THEN 1 ELSE 0 END) AS shift_3_count,
                SUM(CASE WHEN COALESCE(es.shift_id, e.default_shift_id) IS NULL THEN 1 ELSE 0 END) AS no_shift_count,
                COUNT(e.id) AS total_employees,
                SUM(CASE WHEN e.status = 'ACTIVE' THEN 1 ELSE 0 END) AS active_employees
            FROM departments d
            LEFT JOIN employees e ON e.department_id = d.id
            LEFT JOIN employee_shifts es ON es.employee_id = e.id AND es.assigned_date = :today_date
            GROUP BY d.id, d.code, d.name
            ORDER BY total_employees DESC, d.name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':today_date' => $today]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Önümüzdeki 7 günlük planlı vardiya atamalarını döner (Bugünü dahil etmez: today + 1 .. today + 7).
     */
    public function getUpcomingShiftPlan(string $today): array
    {
        $sql = "
            SELECT 
                es.assigned_date,
                s.id AS shift_id,
                s.code AS shift_code,
                s.name AS shift_name,
                s.start_time,
                s.end_time,
                COUNT(es.id) AS employee_count
            FROM employee_shifts es
            INNER JOIN energy_shifts s ON es.shift_id = s.id
            WHERE es.assigned_date BETWEEN DATE_ADD(:today_date, INTERVAL 1 DAY) AND DATE_ADD(:today_date, INTERVAL 7 DAY)
            GROUP BY es.assigned_date, s.id, s.code, s.name, s.start_time, s.end_time
            ORDER BY es.assigned_date ASC, s.id ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':today_date' => $today]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Çalışanlara zimmetli envanter varlıklarının aggregate özetini döner.
     */
    public function getInventorySummary(): array
    {
        $sql = "
            SELECT 
                COUNT(a.id) AS total_assigned_assets,
                SUM(CASE WHEN a.status = 'ASSIGNED' THEN 1 ELSE 0 END) AS status_assigned_count,
                SUM(CASE WHEN a.status = 'IN_USE' THEN 1 ELSE 0 END) AS status_in_use_count,
                SUM(CASE WHEN a.status = 'IN_REPAIR' THEN 1 ELSE 0 END) AS status_in_repair_count,
                COUNT(DISTINCT e.id) AS employees_with_assets
            FROM employees e
            INNER JOIN users u ON u.id = e.user_id
            INNER JOIN inventory_assets a ON a.responsible_user_id = u.id
            WHERE a.is_active = 1
        ";

        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_assigned_assets'  => (int) ($row['total_assigned_assets'] ?? 0),
            'status_assigned_count'  => (int) ($row['status_assigned_count'] ?? 0),
            'status_in_use_count'    => (int) ($row['status_in_use_count'] ?? 0),
            'status_in_repair_count'  => (int) ($row['status_in_repair_count'] ?? 0),
            'employees_with_assets'  => (int) ($row['employees_with_assets'] ?? 0),
        ];
    }

    /**
     * Bugünkü efektif vardiyası NULL olan çalışanları döner (ACTIVE olanlar öncelikli sıralanır).
     */
    public function getUnassignedOrAttentionEmployees(string $today): array
    {
        $sql = "
            SELECT 
                e.id,
                e.registration_no,
                e.first_name,
                e.last_name,
                e.status,
                e.employment_type,
                d.name AS department_name,
                p.title AS position_title
            FROM employees e
            INNER JOIN departments d ON e.department_id = d.id
            INNER JOIN positions p ON e.position_id = p.id
            LEFT JOIN employee_shifts es ON es.employee_id = e.id AND es.assigned_date = :today_date
            WHERE COALESCE(es.shift_id, e.default_shift_id) IS NULL
            ORDER BY (CASE WHEN e.status = 'ACTIVE' THEN 1 ELSE 2 END), d.name ASC, e.first_name ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':today_date' => $today]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
