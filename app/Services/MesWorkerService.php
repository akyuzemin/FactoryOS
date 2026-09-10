<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/MesSimulationService.php';

class MesWorkerService
{
    private static ?string $pidFile = null;

    public static function getPidFilePath(): string
    {
        if (self::$pidFile === null) {
            $storageDir = __DIR__ . '/../../storage';
            if (!is_dir($storageDir)) {
                @mkdir($storageDir, 0777, true);
            }
            self::$pidFile = $storageDir . '/mes_worker.pid';
        }
        return self::$pidFile;
    }

    /**
     * Check if the background worker process is currently running.
     */
    public static function isWorkerRunning(): bool
    {
        $pidFile = self::getPidFilePath();
        if (!file_exists($pidFile)) {
            return false;
        }

        $pid = (int)@file_get_contents($pidFile);
        if ($pid <= 0) {
            @unlink($pidFile);
            return false;
        }

        // Windows Process Check
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = [];
            @exec("tasklist /FI \"PID eq {$pid}\" 2>NUL", $output);
            $running = false;
            foreach ($output as $line) {
                if (str_contains($line, (string)$pid)) {
                    $running = true;
                    break;
                }
            }
            if (!$running) {
                @unlink($pidFile);
            }
            return $running;
        }

        // Linux/Unix Process Check
        if (function_exists('posix_kill')) {
            $running = @posix_kill($pid, 0);
            if (!$running) {
                @unlink($pidFile);
            }
            return $running;
        }

        return file_exists("/proc/{$pid}");
    }

    /**
     * Ensure the background worker is running. If not, launch it detached.
     */
    public static function ensureWorkerRunning(): bool
    {
        if (self::isWorkerRunning()) {
            return true;
        }

        return self::startWorker();
    }

    /**
     * Launch the worker script in the background.
     */
    public static function startWorker(): bool
    {
        $phpBinary = PHP_BINARY;
        if (!file_exists($phpBinary) || str_ends_with(strtolower($phpBinary), 'php-cgi.exe') || str_ends_with(strtolower($phpBinary), 'httpd.exe')) {
            $wampCli = 'C:\\wamp64\\bin\\php\\php8.2.29\\php.exe';
            if (file_exists($wampCli)) {
                $phpBinary = $wampCli;
            } elseif (file_exists('php.exe')) {
                $phpBinary = 'php.exe';
            } else {
                $phpBinary = 'php';
            }
        }

        $scriptPath = realpath(__DIR__ . '/../../scripts/mes_worker.php');
        if (!$scriptPath || !file_exists($scriptPath)) {
            return false;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows background detached spawn
            $cmd = sprintf('start /B "" "%s" "%s" --daemon', $phpBinary, $scriptPath);
            $p = @popen($cmd, "r");
            if ($p !== false) {
                @pclose($p);
            }
        } else {
            // Linux/Unix detached spawn
            $cmd = sprintf('nohup "%s" "%s" --daemon > /dev/null 2>&1 &', $phpBinary, $scriptPath);
            @exec($cmd);
        }

        // Wait brief moment for worker to create PID
        usleep(150000); // 150ms

        return self::isWorkerRunning();
    }

    /**
     * Stop the running worker process if any.
     */
    public static function stopWorker(): bool
    {
        $pidFile = self::getPidFilePath();
        if (!file_exists($pidFile)) {
            return true;
        }

        $pid = (int)@file_get_contents($pidFile);
        if ($pid <= 0) {
            @unlink($pidFile);
            return true;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @exec("taskkill /F /PID {$pid} 2>NUL");
        } elseif (function_exists('posix_kill')) {
            @posix_kill($pid, SIGTERM);
        } else {
            @exec("kill -9 {$pid} 2>/dev/null");
        }

        @unlink($pidFile);
        return true;
    }

    /**
     * Get active worker PID.
     */
    public static function getPid(): ?int
    {
        if (self::isWorkerRunning()) {
            return (int)@file_get_contents(self::getPidFilePath());
        }
        return null;
    }
}

