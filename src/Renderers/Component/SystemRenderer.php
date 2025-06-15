<?php
namespace Wimd\Renderers\Component;

/**
 * SystemRenderer is responsible for rendering system information
 * Cross-platform compatible for Windows, Linux, and macOS
 */
class SystemRenderer extends Component
{
    /**
     * Define health check severity levels
     */
    const HEALTH_OK = 'ok';
    const HEALTH_WARNING = 'warning';
    const HEALTH_ERROR = 'error';

    /**
     * Mapping of severity levels to colors
     */
    protected $severityColors = [
        self::HEALTH_OK => 'green',
        self::HEALTH_WARNING => 'yellow',
        self::HEALTH_ERROR => 'red',
    ];

    /**
     * Mapping of severity levels to emoji
     */
    protected $severityEmojis = [
        self::HEALTH_OK => 'success',
        self::HEALTH_WARNING => 'warning',
        self::HEALTH_ERROR => 'error',
    ];

    /**
     * Detect the current operating system
     *
     * @return string
     */
    protected function getOperatingSystem(): string
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'windows' : 'unix';
    }

    /**
     * Check if running on Windows
     *
     * @return bool
     */
    protected function isWindows(): bool
    {
        return $this->getOperatingSystem() === 'windows';
    }

    /**
     * Get the appropriate directory separator
     *
     * @return string
     */
    protected function getDirectorySeparator(): string
    {
        return $this->isWindows() ? '\\' : '/';
    }

    /**
     * Render system information with enhanced details
     *
     * @return string
     */
    public function renderSystemInfo(): string
    {
        $sysInfo = $this->getExtendedSystemInfo();
        $output = [];

        // System information
        $output[] = $this->createSectionHeader("System information.");

        // Enhanced system information
        $output[] = $this->consoleFormatter->formatLine("Operating System",  "{$sysInfo['os']} gray{({$sysInfo['php_os']})}");
        $output[] = $this->consoleFormatter->formatLine("Database",  "{$sysInfo['database']} gray{(Driver: {$sysInfo['db_driver']})}");
        $output[] = $this->consoleFormatter->formatLine("Environment",  "{$sysInfo['environment']} gray{(App: {$sysInfo['app_name']})}");
        $output[] = $this->consoleFormatter->formatLine("Memory Usage",  "{$sysInfo['memory']} gray{(Peak: {$sysInfo['peak_memory']})}");
        $output[] = $this->consoleFormatter->formatLine("Time",  "{$sysInfo['time']} gray{({$sysInfo['timezone']})}");
        $output[] = $this->consoleFormatter->formatLine("PHP Version",  "{$sysInfo['php_version']}");
        $output[] = $this->consoleFormatter->formatLine("Laravel Version",  "{$sysInfo['laravel_version']}");
        $output[] = "";

        return implode("\n", $output);
    }

    /**
     * Get extended system information for display
     *
     * @return array
     */
    protected function getExtendedSystemInfo(): array
    {
        return [
            'os' => $this->getOperatingSystem() === 'windows' ? 'Windows' : 'Unix-like',
            'php_os' => PHP_OS,
            'database' => config('database.default', 'mysql'),
            'db_driver' => config('database.connections.' . config('database.default') . '.driver', 'unknown'),
            'environment' => app()->environment(),
            'app_name' => config('app.name', 'Laravel'),
            'memory' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
            'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
            'time' => now()->format('Y-m-d H:i:s'),
            'timezone' => config('app.timezone', 'UTC'),
            'php_version' => phpversion(),
            'laravel_version' => app()->version()
        ];
    }

    /**
     * Render a comprehensive health check section
     *
     * @return string
     */
    public function renderHealthCheck(): string
    {
        $output = [];

        $output[] = $this->createSectionHeader("System health check.");
        // Group checks by category
        $dbChecks = $this->performDatabaseChecks();
        $fileSystemChecks = $this->performFileSystemChecks();
        $resourceChecks = $this->performResourceChecks();
        $cacheChecks = $this->performCacheChecks();
        $queueChecks = $this->performQueueChecks();

        // Render all check categories
        $output[] = "<fg=white;options=bold>Database Status:</>";
        foreach ($dbChecks as $check) {
            $output[] = $this->formatHealthCheckResult($check);
        }

        $output[] = "\n<fg=white;options=bold>File System Status:</>";
        foreach ($fileSystemChecks as $check) {
            $output[] = $this->formatHealthCheckResult($check);
        }

        $output[] = "\n<fg=white;options=bold>System Resources:</>";
        foreach ($resourceChecks as $check) {
            $output[] = $this->formatHealthCheckResult($check);
        }

        $output[] = "\n<fg=white;options=bold>Cache Status:</>";
        foreach ($cacheChecks as $check) {
            $output[] = $this->formatHealthCheckResult($check);
        }

        $output[] = "\n<fg=white;options=bold>Queue Status:</>";
        foreach ($queueChecks as $check) {
            $output[] = $this->formatHealthCheckResult($check);
        }

        // Add overall health summary
        $criticalIssues = $this->countIssuesBySeverity($dbChecks, $fileSystemChecks, $resourceChecks, $cacheChecks, $queueChecks, (array)self::HEALTH_ERROR);
        $warnings = $this->countIssuesBySeverity($dbChecks, $fileSystemChecks, $resourceChecks, $cacheChecks, $queueChecks, (array)self::HEALTH_WARNING);

        $output[] = "\n<fg=white;options=bold>Health Summary:</>";
        if ($criticalIssues === 0 && $warnings === 0) {
            $output[] = "All systems operational";
        } else {
            $output[] = sprintf("❗ Found %d critical issues and %d warnings", $criticalIssues, $warnings);
        }

        return implode("\n", $output);
    }

    /**
     * Count issues by severity across all check categories
     *
     * @param array ...$checkGroups
     * @param string $severity
     * @return int
     */
    protected function countIssuesBySeverity(array ...$checkGroups): int
    {
        $severity = array_pop($checkGroups);
        $count = 0;

        foreach ($checkGroups as $checks) {
            foreach ($checks as $check) {
                if ($check['severity'] === $severity) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Format a single health check result for display
     *
     * @param array $check
     * @return string
     */
    protected function formatHealthCheckResult(array $check): string
    {
        $color = $this->severityColors[$check['severity']];
        $output = [];
        $details = null;

        // Add details if available
        if (!empty($check['details'])) {
            $details = " gray{({$check['details']})}";
        }

        $output[] = $this->consoleFormatter->formatLine("{$check['name']}{$details}", "{$color}{{$check['message']}}");
        // Add recommendation if there's an issue
        if ($check['severity'] !== self::HEALTH_OK && !empty($check['recommendation'])) {
            $recommendation = "blue{{$check['recommendation']}}";
            $output[] = $this->consoleFormatter->formatLine($recommendation);
        }
        return implode("\n", $output);
    }

    /**
     * Perform database health checks
     *
     * @return array
     */
    protected function performDatabaseChecks(): array
    {
        $checks = [];

        // Check database connection
        $dbConnected = $this->checkDatabaseConnection();
        $checks[] = [
            'name' => 'Connection',
            'severity' => $dbConnected ? self::HEALTH_OK : self::HEALTH_ERROR,
            'message' => $dbConnected ? 'Connected' : 'Failed',
            'details' => $dbConnected ? config('database.default') : null,
            'recommendation' => $dbConnected ? null : 'Check database credentials and make sure the database server is running.'
        ];

        // Only proceed with other DB checks if connected
        if ($dbConnected) {
            // Check for migrations
            $pendingMigrations = $this->checkPendingMigrations();
            $checks[] = [
                'name' => 'Migrations',
                'severity' => $pendingMigrations ? self::HEALTH_WARNING : self::HEALTH_OK,
                'message' => $pendingMigrations ? 'Pending migrations' : 'Up to date',
                'details' => $pendingMigrations ? "{$pendingMigrations} pending" : null,
                'recommendation' => $pendingMigrations ? 'Run `php artisan migrate` to apply pending migrations.' : null
            ];

            // Check query performance
            $slowQueries = $this->checkSlowQueries();
            $checks[] = [
                'name' => 'Query Performance',
                'severity' => $slowQueries > 5 ? self::HEALTH_WARNING : self::HEALTH_OK,
                'message' => $slowQueries > 5 ? 'Slow queries detected' : 'Normal',
                'details' => $slowQueries > 0 ? "{$slowQueries} slow queries" : null,
                'recommendation' => $slowQueries > 5 ? 'Check slow query log and optimize problematic queries.' : null
            ];
        }

        return $checks;
    }

    /**
     * Perform file system checks
     *
     * @return array
     */
    protected function performFileSystemChecks(): array
    {
        $checks = [];

        // Check storage directories with cross-platform paths
        $paths = [
            'storage' => $this->normalizePath(storage_path()),
            'cache' => $this->normalizePath(storage_path('framework/cache')),
            'views' => $this->normalizePath(storage_path('framework/views')),
            'logs' => $this->normalizePath(storage_path('logs')),
            'public' => $this->normalizePath(public_path('storage'))
        ];

        foreach ($paths as $name => $path) {
            $exists = file_exists($path);
            $writable = $exists && is_writable($path);

            $severity = self::HEALTH_OK;
            $message = 'Writable';
            $recommendation = null;

            if (!$exists) {
                $severity = self::HEALTH_ERROR;
                $message = 'Missing';
                $recommendation = "Create the directory: {$path}";
            } elseif (!$writable) {
                $severity = self::HEALTH_ERROR;
                $message = 'Not writable';
                $recommendation = $this->getPermissionFixRecommendation($path);
            }

            $checks[] = [
                'name' => ucfirst($name) . ' directory',
                'severity' => $severity,
                'message' => $message,
                'details' => $path,
                'recommendation' => $recommendation
            ];
        }

        // Check for symbolic links
        $symlinksCreated = file_exists(public_path('storage'));
        $checks[] = [
            'name' => 'Storage symlinks',
            'severity' => $symlinksCreated ? self::HEALTH_OK : self::HEALTH_WARNING,
            'message' => $symlinksCreated ? 'Created' : 'Missing',
            'details' => null,
            'recommendation' => $symlinksCreated ? null : 'Run `php artisan storage:link` to create the symbolic link.'
        ];

        return $checks;
    }

    /**
     * Perform system resource checks
     *
     * @return array
     */
    protected function performResourceChecks(): array
    {
        $checks = [];

        // Check memory usage
        $memoryUsage = memory_get_usage() / 1024 / 1024;
        $memoryLimit = $this->getMemoryLimitInMB();
        $memoryPercentage = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;

        $memorySeverity = self::HEALTH_OK;
        $memoryMessage = 'Normal';
        $memoryRecommendation = null;

        if ($memoryPercentage > 80) {
            $memorySeverity = self::HEALTH_ERROR;
            $memoryMessage = 'Critical';
            $memoryRecommendation = 'Increase PHP memory_limit in php.ini or check for memory leaks.';
        } elseif ($memoryPercentage > 60) {
            $memorySeverity = self::HEALTH_WARNING;
            $memoryMessage = 'High';
            $memoryRecommendation = 'Monitor memory usage patterns and optimize if necessary.';
        }

        $memoryDetails = round($memoryUsage, 2) . " MB of " .
            ($memoryLimit > 0 ? $memoryLimit . " MB" : "unlimited") .
            " (" . round($memoryPercentage, 1) . "%)";

        $checks[] = [
            'name' => 'Memory usage',
            'severity' => $memorySeverity,
            'message' => $memoryMessage,
            'details' => $memoryDetails,
            'recommendation' => $memoryRecommendation
        ];

        // Check disk space
        $diskFree = disk_free_space(base_path());
        $diskTotal = disk_total_space(base_path());

        if ($diskFree !== false && $diskTotal !== false) {
            $diskUsagePercentage = ($diskTotal - $diskFree) / $diskTotal * 100;

            $diskSeverity = self::HEALTH_OK;
            $diskMessage = 'Normal';
            $diskRecommendation = null;

            if ($diskUsagePercentage > 90) {
                $diskSeverity = self::HEALTH_ERROR;
                $diskMessage = 'Critical';
                $diskRecommendation = 'Free up disk space or increase disk capacity.';
            } elseif ($diskUsagePercentage > 70) {
                $diskSeverity = self::HEALTH_WARNING;
                $diskMessage = 'High';
                $diskRecommendation = 'Monitor disk usage and plan for cleanup or expansion.';
            }

            $diskDetails = $this->formatBytes($diskFree) . " free of " .
                $this->formatBytes($diskTotal) . " (" .
                round($diskUsagePercentage, 1) . "% used)";

            $checks[] = [
                'name' => 'Disk space',
                'severity' => $diskSeverity,
                'message' => $diskMessage,
                'details' => $diskDetails,
                'recommendation' => $diskRecommendation
            ];
        } else {
            $checks[] = [
                'name' => 'Disk space',
                'severity' => self::HEALTH_WARNING,
                'message' => 'Unable to check',
                'details' => 'Disk space functions not available',
                'recommendation' => 'Check disk space manually using system tools.'
            ];
        }

        // Check PHP extensions
        $requiredExtensions = ['pdo', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath'];
        $missingExtensions = [];

        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $missingExtensions[] = $extension;
            }
        }

        $extSeverity = empty($missingExtensions) ? self::HEALTH_OK : self::HEALTH_ERROR;
        $extMessage = empty($missingExtensions) ? 'All installed' : 'Missing extensions';
        $extDetails = empty($missingExtensions) ? null : implode(', ', $missingExtensions);
        $extRecommendation = empty($missingExtensions) ? null : $this->getExtensionInstallRecommendation($missingExtensions);

        $checks[] = [
            'name' => 'PHP extensions',
            'severity' => $extSeverity,
            'message' => $extMessage,
            'details' => $extDetails,
            'recommendation' => $extRecommendation
        ];

        return $checks;
    }

    /**
     * Perform cache system checks
     *
     * @return array
     */
    protected function performCacheChecks(): array
    {
        $checks = [];

        // Check cache driver
        $cacheDriver = config('cache.default');
        $cacheDrivers = ['file', 'database', 'redis', 'memcached', 'array'];

        $cacheSeverity = in_array($cacheDriver, $cacheDrivers) ? self::HEALTH_OK : self::HEALTH_WARNING;
        $cacheMessage = in_array($cacheDriver, $cacheDrivers) ? 'Available' : 'Unknown driver';
        $cacheRecommendation = in_array($cacheDriver, $cacheDrivers) ? null : 'Configure a supported cache driver in .env file.';

        $checks[] = [
            'name' => 'Cache driver',
            'severity' => $cacheSeverity,
            'message' => $cacheMessage,
            'details' => $cacheDriver,
            'recommendation' => $cacheRecommendation
        ];

        // Check cache connectivity
        $cacheConnected = $this->checkCacheConnection();
        $checks[] = [
            'name' => 'Cache connection',
            'severity' => $cacheConnected ? self::HEALTH_OK : self::HEALTH_ERROR,
            'message' => $cacheConnected ? 'Connected' : 'Failed',
            'details' => null,
            'recommendation' => $cacheConnected ? null : 'Check cache configuration and ensure the cache service is running.'
        ];

        return $checks;
    }

    /**
     * Perform queue system checks
     *
     * @return array
     */
    protected function performQueueChecks(): array
    {
        $checks = [];

        // Check queue driver
        $queueDriver = config('queue.default');
        $queueDrivers = ['sync', 'database', 'redis', 'beanstalkd', 'sqs', 'null'];

        $queueSeverity = in_array($queueDriver, $queueDrivers) ? self::HEALTH_OK : self::HEALTH_WARNING;
        $queueMessage = in_array($queueDriver, $queueDrivers) ? 'Available' : 'Unknown driver';
        $queueRecommendation = in_array($queueDriver, $queueDrivers) ? null : 'Configure a supported queue driver in .env file.';

        $checks[] = [
            'name' => 'Queue driver',
            'severity' => $queueSeverity,
            'message' => $queueMessage,
            'details' => $queueDriver,
            'recommendation' => $queueRecommendation
        ];

        // Check for stuck jobs in queue
        if ($queueDriver === 'database') {
            $stuckJobs = $this->checkStuckJobs();
            $queueJobSeverity = $stuckJobs > 0 ? self::HEALTH_WARNING : self::HEALTH_OK;
            $queueJobMessage = $stuckJobs > 0 ? 'Stuck jobs found' : 'No stuck jobs';
            $queueJobRecommendation = $stuckJobs > 0 ? 'Run `php artisan queue:retry all` to retry failed jobs.' : null;

            $checks[] = [
                'name' => 'Queue jobs',
                'severity' => $queueJobSeverity,
                'message' => $queueJobMessage,
                'details' => $stuckJobs > 0 ? "{$stuckJobs} stuck jobs" : null,
                'recommendation' => $queueJobRecommendation
            ];
        }

        // Check queue worker status
        $workersRunning = $this->checkQueueWorkers();
        $workerSeverity = $workersRunning || $queueDriver === 'sync' ? self::HEALTH_OK : self::HEALTH_WARNING;
        $workerMessage = $workersRunning ? 'Running' : ($queueDriver === 'sync' ? 'Not applicable' : 'Not running');
        $workerRecommendation = (!$workersRunning && $queueDriver !== 'sync') ?
            'Start queue workers with `php artisan queue:work` or use a process manager like Supervisor (Linux/macOS) or NSSM (Windows).' : null;

        $checks[] = [
            'name' => 'Queue workers',
            'severity' => $workerSeverity,
            'message' => $workerMessage,
            'details' => null,
            'recommendation' => $workerRecommendation
        ];

        return $checks;
    }

    /**
     * Check the database connection
     *
     * @return bool
     */
    protected function checkDatabaseConnection(): bool
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check for pending migrations
     *
     * @return int Number of pending migrations
     */
    protected function checkPendingMigrations(): int
    {
        try {
            $migrator = app('migrator');
            $pending = $migrator->repositoryExists() ?
                count($migrator->getMigrationFiles(database_path('migrations'))) -
                count($migrator->getRepository()->getRan()) : 0;
            return $pending;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Check for slow queries
     *
     * @return int Number of slow queries
     */
    protected function checkSlowQueries(): int
    {
        try {
            // This is a placeholder - actual implementation would depend on the
            // query logging mechanism in use (e.g., MySQL slow query log, Laravel's query log)
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Check cache connection
     *
     * @return bool
     */
    protected function checkCacheConnection(): bool
    {
        try {
            $cacheDriver = config('cache.default');
            if ($cacheDriver === 'array') {
                return true;
            }

            // Test cache connection by setting and retrieving a value
            $key = 'health_check_' . time();
            \Cache::put($key, 'test', 1);
            return \Cache::has($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check for stuck jobs in queue
     *
     * @return int Number of stuck jobs
     */
    protected function checkStuckJobs(): int
    {
        try {
            // Check for failed jobs
            $failedCount = 0;
            if (class_exists('\Illuminate\Queue\Failed\FailedJobProviderInterface')) {
                $failedCount = app('\Illuminate\Queue\Failed\FailedJobProviderInterface')->count();
            }

            // Check for jobs that have been attempted too many times
            $stuckCount = 0;
            if (config('queue.default') === 'database') {
                $stuckCount = \DB::table(config('queue.connections.database.table', 'jobs'))
                    ->where('attempts', '>=', 3)
                    ->count();
            }

            return $failedCount + $stuckCount;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Check if queue workers are running (cross-platform)
     *
     * @return bool
     */
    protected function checkQueueWorkers(): bool
    {
        try {
            if (!function_exists('exec')) {
                return false;
            }

            if ($this->isWindows()) {
                // Windows: Use tasklist to check for PHP processes running queue commands
                exec('tasklist /FI "IMAGENAME eq php.exe" /FO CSV 2>NUL', $output, $returnCode);
                if ($returnCode === 0) {
                    foreach ($output as $line) {
                        if (strpos($line, 'php.exe') !== false) {
                            // Additional check to see if it's actually running queue commands
                            exec('wmic process where "name=\'php.exe\'" get CommandLine /format:list 2>NUL', $cmdOutput);
                            foreach ($cmdOutput as $cmdLine) {
                                if (strpos($cmdLine, 'queue:work') !== false || strpos($cmdLine, 'queue:listen') !== false) {
                                    return true;
                                }
                            }
                        }
                    }
                }
            } else {
                // Unix-like systems: Use ps command
                exec('ps aux | grep "queue:work\|queue:listen" | grep -v grep', $output);
                return !empty($output);
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get PHP memory limit in MB
     *
     * @return float
     */
    protected function getMemoryLimitInMB(): float
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return 0; // Unlimited
        }

        $unit = strtolower(substr($memoryLimit, -1));
        $value = (float)substr($memoryLimit, 0, -1);

        return match($unit) {
            'g' => $value * 1024,
            'm' => $value,
            'k' => $value / 1024,
            default => (float)$memoryLimit / 1048576,
        };
    }

    /**
     * Format bytes into human readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    protected function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Normalize file paths for cross-platform compatibility
     *
     * @param string $path
     * @return string
     */
    protected function normalizePath(string $path): string
    {
        // Convert all directory separators to the appropriate one for the current OS
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Remove duplicate separators
        $path = preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR) . '+#', DIRECTORY_SEPARATOR, $path);

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Get OS-specific permission fix recommendation
     *
     * @param string $path
     * @return string
     */
    protected function getPermissionFixRecommendation(string $path): string
    {
        if ($this->isWindows()) {
            return "Fix permissions: Right-click folder → Properties → Security → Edit permissions for 'Users' group or run `icacls \"{$path}\" /grant Users:F /T`";
        } else {
            return "Fix permissions: `chmod -R 775 {$path}` and ensure correct ownership";
        }
    }

    /**
     * Get OS-specific PHP extension installation recommendation
     *
     * @param array $missingExtensions
     * @return string
     */
    protected function getExtensionInstallRecommendation(array $missingExtensions): string
    {
        $extensionsList = implode(', ', $missingExtensions);

        if ($this->isWindows()) {
            return "Install missing PHP extensions ({$extensionsList}) by editing php.ini and uncommenting the corresponding extension lines, or download DLL files and add them to the extensions directory.";
        } else {
            return "Install missing PHP extensions ({$extensionsList}) via your system package manager (e.g., `apt install php-{extension}` on Ubuntu, `yum install php-{extension}` on RHEL/CentOS).";
        }
    }

    /**
     * Get system-specific temporary directory
     *
     * @return string
     */
    protected function getTempDirectory(): string
    {
        if ($this->isWindows()) {
            return $_SERVER['TEMP'] ?? $_SERVER['TMP'] ?? sys_get_temp_dir();
        } else {
            return sys_get_temp_dir();
        }
    }

    /**
     * Check if a command exists in the system PATH
     *
     * @param string $command
     * @return bool
     */
    protected function commandExists(string $command): bool
    {
        if ($this->isWindows()) {
            $result = shell_exec("where {$command} 2>NUL");
            return !empty($result);
        } else {
            $result = shell_exec("which {$command} 2>/dev/null");
            return !empty($result);
        }
    }

    /**
     * Get process list command based on OS
     *
     * @return string
     */
    protected function getProcessListCommand(): string
    {
        if ($this->isWindows()) {
            return 'tasklist';
        } else {
            return 'ps aux';
        }
    }

    /**
     * Enhanced system service checks (cross-platform)
     *
     * @return array
     */
    protected function performServiceChecks(): array
    {
        $checks = [];

        // Check web server
        $webServerRunning = $this->checkWebServer();
        $checks[] = [
            'name' => 'Web Server',
            'severity' => $webServerRunning ? self::HEALTH_OK : self::HEALTH_WARNING,
            'message' => $webServerRunning ? 'Running' : 'Not detected',
            'details' => $this->getWebServerInfo(),
            'recommendation' => $webServerRunning ? null : 'Ensure your web server (Apache/Nginx/IIS) is running.'
        ];

        // Check database service
        if (config('database.default') !== 'sqlite') {
            $dbServiceRunning = $this->checkDatabaseService();
            $checks[] = [
                'name' => 'Database Service',
                'severity' => $dbServiceRunning ? self::HEALTH_OK : self::HEALTH_ERROR,
                'message' => $dbServiceRunning ? 'Running' : 'Not running',
                'details' => config('database.default'),
                'recommendation' => $dbServiceRunning ? null : $this->getDatabaseServiceRecommendation()
            ];
        }

        // Check Redis service (if configured)
        if (config('cache.default') === 'redis' || config('queue.default') === 'redis') {
            $redisRunning = $this->checkRedisService();
            $checks[] = [
                'name' => 'Redis Service',
                'severity' => $redisRunning ? self::HEALTH_OK : self::HEALTH_ERROR,
                'message' => $redisRunning ? 'Running' : 'Not running',
                'details' => 'Port: ' . (config('database.redis.default.port') ?? '6379'),
                'recommendation' => $redisRunning ? null : $this->getRedisServiceRecommendation()
            ];
        }

        return $checks;
    }

    /**
     * Check if web server is running
     *
     * @return bool
     */
    protected function checkWebServer(): bool
    {
        try {
            // Check common web server ports
            $ports = [80, 443, 8000, 8080];

            foreach ($ports as $port) {
                if ($this->isPortOpen('127.0.0.1', $port)) {
                    return true;
                }
            }

            // Also check if we can detect server software
            if (isset($_SERVER['SERVER_SOFTWARE'])) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get web server information
     *
     * @return string|null
     */
    protected function getWebServerInfo(): ?string
    {
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            return $_SERVER['SERVER_SOFTWARE'];
        }

        // Try to detect web server by checking processes
        if ($this->isWindows()) {
            $servers = ['httpd.exe', 'nginx.exe', 'iisexpress.exe', 'w3wp.exe'];
            foreach ($servers as $server) {
                if ($this->isProcessRunning($server)) {
                    return ucfirst(str_replace('.exe', '', $server));
                }
            }
        } else {
            $servers = ['apache2', 'httpd', 'nginx'];
            foreach ($servers as $server) {
                if ($this->isProcessRunning($server)) {
                    return ucfirst($server);
                }
            }
        }

        return null;
    }

    /**
     * Check if database service is running
     *
     * @return bool
     */
    protected function checkDatabaseService(): bool
    {
        $driver = config('database.default');
        $connection = config("database.connections.{$driver}");

        if (!$connection) {
            return false;
        }

        $processes = $this->getDatabaseProcessNames($driver);

        foreach ($processes as $process) {
            if ($this->isProcessRunning($process)) {
                return true;
            }
        }

        // Also try to connect to the database port
        $host = $connection['host'] ?? 'localhost';
        $port = $connection['port'] ?? $this->getDefaultDatabasePort($driver);

        return $this->isPortOpen($host, $port);
    }

    /**
     * Check if Redis service is running
     *
     * @return bool
     */
    protected function checkRedisService(): bool
    {
        try {
            $host = config('database.redis.default.host', '127.0.0.1');
            $port = config('database.redis.default.port', 6379);

            // Check if Redis process is running
            $redisProcesses = $this->isWindows() ? ['redis-server.exe'] : ['redis-server'];

            foreach ($redisProcesses as $process) {
                if ($this->isProcessRunning($process)) {
                    return true;
                }
            }

            // Check if Redis port is open
            return $this->isPortOpen($host, $port);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a specific process is running
     *
     * @param string $processName
     * @return bool
     */
    protected function isProcessRunning(string $processName): bool
    {
        try {
            if (!function_exists('exec')) {
                return false;
            }

            if ($this->isWindows()) {
                exec("tasklist /FI \"IMAGENAME eq {$processName}\" 2>NUL", $output, $returnCode);
                return $returnCode === 0 && count($output) > 1;
            } else {
                exec("pgrep -f {$processName} 2>/dev/null", $output, $returnCode);
                return $returnCode === 0 && !empty($output);
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a port is open on a host
     *
     * @param string $host
     * @param int $port
     * @param int $timeout
     * @return bool
     */
    protected function isPortOpen(string $host, int $port, int $timeout = 3): bool
    {
        try {
            $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
            if ($connection) {
                fclose($connection);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get database process names by driver
     *
     * @param string $driver
     * @return array
     */
    protected function getDatabaseProcessNames(string $driver): array
    {
        $processes = [
            'mysql' => $this->isWindows() ? ['mysqld.exe', 'mysql.exe'] : ['mysqld', 'mysql'],
            'pgsql' => $this->isWindows() ? ['postgres.exe', 'pg_ctl.exe'] : ['postgres', 'postmaster'],
            'sqlsrv' => $this->isWindows() ? ['sqlservr.exe'] : [],
            'sqlite' => [], // SQLite doesn't run as a service
        ];

        return $processes[$driver] ?? [];
    }

    /**
     * Get default database port by driver
     *
     * @param string $driver
     * @return int
     */
    protected function getDefaultDatabasePort(string $driver): int
    {
        $ports = [
            'mysql' => 3306,
            'pgsql' => 5432,
            'sqlsrv' => 1433,
            'sqlite' => 0, // SQLite doesn't use ports
        ];

        return $ports[$driver] ?? 0;
    }

    /**
     * Get database service recommendation
     *
     * @return string
     */
    protected function getDatabaseServiceRecommendation(): string
    {
        $driver = config('database.default');

        if ($this->isWindows()) {
            $services = [
                'mysql' => 'Start MySQL service: `net start mysql` or use Services.msc',
                'pgsql' => 'Start PostgreSQL service: `net start postgresql-x64-13` or use Services.msc',
                'sqlsrv' => 'Start SQL Server service: `net start mssqlserver` or use Services.msc',
            ];
        } else {
            $services = [
                'mysql' => 'Start MySQL service: `sudo systemctl start mysql` or `sudo service mysql start`',
                'pgsql' => 'Start PostgreSQL service: `sudo systemctl start postgresql` or `sudo service postgresql start`',
                'sqlsrv' => 'SQL Server support varies by distribution. Check Microsoft documentation.',
            ];
        }

        return $services[$driver] ?? 'Check your database service configuration and ensure it\'s running.';
    }

    /**
     * Get Redis service recommendation
     *
     * @return string
     */
    protected function getRedisServiceRecommendation(): string
    {
        if ($this->isWindows()) {
            return 'Start Redis service: Download Redis for Windows and start redis-server.exe, or use Windows Subsystem for Linux (WSL).';
        } else {
            return 'Start Redis service: `sudo systemctl start redis` or `sudo service redis-server start`';
        }
    }

    /**
     * Perform environment-specific checks
     *
     * @return array
     */
    protected function performEnvironmentChecks(): array
    {
        $checks = [];

        // Check .env file existence and readability
        $envPath = base_path('.env');
        $envExists = file_exists($envPath);
        $envReadable = $envExists && is_readable($envPath);

        $checks[] = [
            'name' => 'Environment file',
            'severity' => $envReadable ? self::HEALTH_OK : self::HEALTH_ERROR,
            'message' => $envReadable ? 'Accessible' : ($envExists ? 'Not readable' : 'Missing'),
            'details' => $envPath,
            'recommendation' => $envReadable ? null : ($envExists ? 'Fix file permissions for .env file' : 'Copy .env.example to .env and configure your environment variables')
        ];

        // Check APP_KEY
        $appKey = config('app.key');
        $checks[] = [
            'name' => 'Application key',
            'severity' => !empty($appKey) ? self::HEALTH_OK : self::HEALTH_ERROR,
            'message' => !empty($appKey) ? 'Set' : 'Missing',
            'details' => !empty($appKey) ? 'Configured' : null,
            'recommendation' => !empty($appKey) ? null : 'Generate application key: `php artisan key:generate`'
        ];

        // Check debug mode in production
        $isProduction = app()->environment('production');
        $debugEnabled = config('app.debug');

        if ($isProduction && $debugEnabled) {
            $checks[] = [
                'name' => 'Debug mode',
                'severity' => self::HEALTH_WARNING,
                'message' => 'Enabled in production',
                'details' => 'APP_DEBUG=true',
                'recommendation' => 'Disable debug mode in production: Set APP_DEBUG=false in .env file'
            ];
        } else {
            $checks[] = [
                'name' => 'Debug mode',
                'severity' => self::HEALTH_OK,
                'message' => $isProduction ? 'Disabled (production)' : 'Enabled (development)',
                'details' => 'APP_DEBUG=' . ($debugEnabled ? 'true' : 'false'),
                'recommendation' => null
            ];
        }

        // Check timezone configuration
        $appTimezone = config('app.timezone');
        $systemTimezone = date_default_timezone_get();

        $checks[] = [
            'name' => 'Timezone configuration',
            'severity' => self::HEALTH_OK,
            'message' => 'Configured',
            'details' => "App: {$appTimezone}, System: {$systemTimezone}",
            'recommendation' => $appTimezone !== $systemTimezone ? 'Consider matching application and system timezones for consistency' : null
        ];

        return $checks;
    }

    /**
     * Perform security checks
     *
     * @return array
     */
    protected function performSecurityChecks(): array
    {
        $checks = [];

        // Check if sensitive files are accessible via web
        $sensitiveFiles = ['.env', 'composer.json', 'composer.lock', 'artisan'];
        $accessibleFiles = [];

        foreach ($sensitiveFiles as $file) {
            if (file_exists(public_path($file))) {
                $accessibleFiles[] = $file;
            }
        }

        $checks[] = [
            'name' => 'Sensitive files exposure',
            'severity' => empty($accessibleFiles) ? self::HEALTH_OK : self::HEALTH_ERROR,
            'message' => empty($accessibleFiles) ? 'Protected' : 'Exposed files detected',
            'details' => empty($accessibleFiles) ? null : implode(', ', $accessibleFiles),
            'recommendation' => empty($accessibleFiles) ? null : 'Move sensitive files outside of public directory or configure web server to deny access'
        ];

        // Check directory permissions
        $criticalDirs = [
            storage_path(),
            storage_path('logs'),
            bootstrap_path('cache')
        ];

        $permissionIssues = [];
        foreach ($criticalDirs as $dir) {
            if (file_exists($dir) && !is_writable($dir)) {
                $permissionIssues[] = basename($dir);
            }
        }

        $checks[] = [
            'name' => 'Directory permissions',
            'severity' => empty($permissionIssues) ? self::HEALTH_OK : self::HEALTH_ERROR,
            'message' => empty($permissionIssues) ? 'Secure' : 'Permission issues',
            'details' => empty($permissionIssues) ? null : 'Issues with: ' . implode(', ', $permissionIssues),
            'recommendation' => empty($permissionIssues) ? null : $this->getPermissionFixRecommendation(implode(', ', $permissionIssues))
        ];

        // Check HTTPS configuration (basic check)
        $httpsConfigured = config('app.url') && str_starts_with(config('app.url'), 'https://');
        $forceHttps = config('session.secure', false);

        $checks[] = [
            'name' => 'HTTPS configuration',
            'severity' => $httpsConfigured ? self::HEALTH_OK : self::HEALTH_WARNING,
            'message' => $httpsConfigured ? 'Configured' : 'Not configured',
            'details' => $httpsConfigured ? 'SSL/TLS enabled' : 'HTTP only',
            'recommendation' => $httpsConfigured ? null : 'Configure HTTPS for production: Update APP_URL and enable secure session cookies'
        ];

        return $checks;
    }

    /**
     * Enhanced health check with additional categories
     *
     * @return string
     */
    public function renderEnhancedHealthCheck(): string
    {
        $output = [];

        $output[] = $this->createSectionHeader("COMPREHENSIVE SYSTEM HEALTH CHECK");

        // Get all check categories
        $categories = [
            'Database' => $this->performDatabaseChecks(),
            'File System' => $this->performFileSystemChecks(),
            'System Resources' => $this->performResourceChecks(),
            'Cache' => $this->performCacheChecks(),
            'Queue' => $this->performQueueChecks(),
            'Services' => $this->performServiceChecks(),
            'Environment' => $this->performEnvironmentChecks(),
            'Security' => $this->performSecurityChecks(),
        ];

        // Render all categories
        foreach ($categories as $categoryName => $checks) {
            $output[] = "\n<fg=white;options=bold>{$categoryName} Status:</>";
            foreach ($checks as $check) {
                $output[] = $this->formatHealthCheckResult($check);
            }
        }

        // Calculate overall health summary
        $allChecks = array_merge(...array_values($categories));
        $criticalIssues = array_filter($allChecks, fn($check) => $check['severity'] === self::HEALTH_ERROR);
        $warnings = array_filter($allChecks, fn($check) => $check['severity'] === self::HEALTH_WARNING);

        $output[] = "\n<fg=white;options=bold>Overall Health Summary:</>";
        $output[] = sprintf("📊 Total checks performed: %d", count($allChecks));
        $output[] = sprintf("🔴 Critical issues: %d", count($criticalIssues));
        $output[] = sprintf("🟡 Warnings: %d", count($warnings));
        $output[] = sprintf("🟢 Healthy checks: %d", count($allChecks) - count($criticalIssues) - count($warnings));

        if (empty($criticalIssues) && empty($warnings)) {
            $output[] = "\n✅ <fg=green;options=bold>All systems are operational!</>";
        } else {
            $healthPercentage = ((count($allChecks) - count($criticalIssues) - count($warnings)) / count($allChecks)) * 100;
            $output[] = sprintf("\n📈 <fg=white;options=bold>System health: %.1f%%</> %s",
                $healthPercentage,
                $healthPercentage >= 90 ? '(Excellent)' :
                    ($healthPercentage >= 70 ? '(Good)' :
                        ($healthPercentage >= 50 ? '(Fair)' : '(Needs attention)'))
            );
        }

        return implode("\n", $output);
    }

    /**
     * Generate a system report with recommendations
     *
     * @return string
     */
    public function generateSystemReport(): string
    {
        $output = [];

        $output[] = $this->createSectionHeader("SYSTEM HEALTH REPORT");
        $output[] = "Generated: " . now()->format('Y-m-d H:i:s T');
        $output[] = "Environment: " . app()->environment();
        $output[] = "OS: " . PHP_OS . " (" . ($this->isWindows() ? 'Windows' : 'Unix-like') . ")";
        $output[] = "";

        // Add system information
        $output[] = $this->renderSystemInfo();
        $output[] = "";

        // Add comprehensive health check
        $output[] = $this->renderEnhancedHealthCheck();

        return implode("\n", $output);
    }
}
