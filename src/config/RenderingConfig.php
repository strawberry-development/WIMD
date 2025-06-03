<?php

namespace Wimd\Config;

use InvalidArgumentException;

/**
 * RenderingConfig
 *
 * Handles the configuration logic for rendering and output behavior of the WIMD package.
 *
 * Configuration is merged from default values, Laravel config files, and user overrides.
 * This class also validates settings and provides convenience methods for querying and modifying configuration.
 */
class RenderingConfig
{
    /**
     * Configuration settings
     */
    private array $settings;

    /**
     * Default configuration values
     */
    private const DEFAULT_CONFIG = [
        'mode' => 'full',
        'display' => [
            'detailed_table' => true,
            'system_info' => true,
            'performance_distribution' => true,
            'performance_charts' => true,
            'recommendations' => true,
        ],
        'styling' => [
            'use_unicode' => true,
            'use_emojis' => true,
            'use_colors' => true,
            'border_style' => 'rounded',
            'progress_format' => [
                'bar' => '[%bar%] %percent:3s%%',
                'base' => '%elapsed:6s% spend / %remaining:-6s% left',
                'full' => '| Memory %memory:6s%s'
            ]
        ],
        'thresholds' => [
            'excellent' => 1000,
            'good' => 500,
            'average' => 100,
            'slow' => 10
        ],
        'memory' => [
            'warnings_enabled' => true,
            'thresholds' => [
                'notice' => '50M',
                'warning' => '100M',
                'critical' => '200M',
            ],
            'per_record' => [
                'efficient' => 1,
                'acceptable' => 5,
                'concerning' => 20,
                'excessive' => 50
            ],
            'options' => [
                'display_during_seeding' => true,
                'log_excessive_usage' => true,
                'abort_threshold' => null,
                'show_optimization_tips' => true,
                'track_usage_over_time' => false,
            ],
        ],
        'debug' => [
            'verbose' => false,
            'log_to_file' => false,
            'log_file' => null
        ],
    ];

    /**
     * Valid display modes
     */
    private const VALID_MODES = ['full', 'light'];

    /**
     * Valid progress format types
     */
    private const VALID_PROGRESS_TYPES = ['bar', 'base', 'full'];

    /**
     * Valid performance threshold levels
     */
    private const VALID_PERFORMANCE_LEVELS = ['excellent', 'good', 'average', 'slow'];

    /**
     * Valid memory threshold levels
     */
    private const VALID_MEMORY_LEVELS = ['notice', 'warning', 'critical'];

    /**
     * Valid per-record memory levels
     */
    private const VALID_PER_RECORD_LEVELS = ['efficient', 'acceptable', 'concerning', 'excessive'];

    /**
     * Constructor
     *
     * @param array $customSettings Optional custom configuration to override config file values
     */
    public function __construct(array $customSettings = [])
    {
        $this->loadConfiguration($customSettings);
    }

    /**
     * Load and merge configuration
     */
    private function loadConfiguration(array $customSettings = []): void
    {
        // Load configuration from Laravel config file
        $configFromFile = config('wimd', []);

        // Merge configurations: defaults -> file config -> custom settings
        $this->settings = array_replace_recursive(
            self::DEFAULT_CONFIG,
            $configFromFile,
            $customSettings
        );

        $this->validateConfiguration();
    }

    /**
     * Validate configuration values
     */
    private function validateConfiguration(): void
    {
        // Validate mode
        if (!in_array($this->settings['mode'], self::VALID_MODES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid mode "%s". Valid modes are: %s',
                    $this->settings['mode'],
                    implode(', ', self::VALID_MODES)
                )
            );
        }

        // Ensure debug log file has a default if logging is enabled
        if ($this->settings['debug']['log_to_file'] && empty($this->settings['debug']['log_file'])) {
            $this->settings['debug']['log_file'] = storage_path('logs/wimd-seeding.log');
        }
    }

    // =============================
    // MODE METHODS
    // =============================

    public function getMode(): string
    {
        return $this->settings['mode'];
    }

    public function setMode(string $mode): self
    {
        if (!in_array($mode, self::VALID_MODES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid mode "%s". Valid modes are: %s',
                    $mode,
                    implode(', ', self::VALID_MODES)
                )
            );
        }

        $this->settings['mode'] = $mode;
        return $this;
    }

    public function isMode(string $mode): bool
    {
        return $this->settings['mode'] === $mode;
    }

    // =============================
    // DISPLAY METHODS
    // =============================

    public function isDisplayEnabled(string $component): bool
    {
        return $this->settings['display'][$component] ?? false;
    }

    public function getDisplaySettings(): array
    {
        return $this->settings['display'];
    }

    public function setDisplayEnabled(string $component, bool $enabled): self
    {
        $this->settings['display'][$component] = $enabled;
        return $this;
    }

    public function enableAllDisplayComponents(): self
    {
        foreach (array_keys($this->settings['display']) as $component) {
            $this->settings['display'][$component] = true;
        }
        return $this;
    }

    public function disableAllDisplayComponents(): self
    {
        foreach (array_keys($this->settings['display']) as $component) {
            $this->settings['display'][$component] = false;
        }
        return $this;
    }

    // =============================
    // STYLING METHODS
    // =============================

    public function isEmojisEnabled(): bool
    {
        return $this->settings['styling']['use_emojis'] ?? true;
    }

    public function isColorsEnabled(): bool
    {
        return $this->settings['styling']['use_colors'] ?? true;
    }

    public function getStylingSettings(): array
    {
        return $this->settings['styling'];
    }

    public function setStylingOption(string $option, mixed $value): self
    {
        $this->settings['styling'][$option] = $value;
        return $this;
    }

    // =============================
    // PROGRESS FORMAT METHODS
    // =============================

    public function getProgressFormat(string $type = 'base'): string
    {
        if (!in_array($type, self::VALID_PROGRESS_TYPES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid progress type "%s". Valid types are: %s',
                    $type,
                    implode(', ', self::VALID_PROGRESS_TYPES)
                )
            );
        }

        $formats = $this->settings['styling']['progress_format'] ?? [];
        return $formats[$type] ?? $formats['base'] ?? '[%bar%] %percent:3s%%';
    }

    public function getProgressFormats(): array
    {
        return $this->settings['styling']['progress_format'] ?? [];
    }

    public function getFullProgressFormat(): string
    {
        return $this->getProgressFormat('base') . $this->getProgressFormat('full');
    }

    public function setProgressFormat(string $type, string $format): self
    {
        if (!in_array($type, self::VALID_PROGRESS_TYPES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid progress type "%s". Valid types are: %s',
                    $type,
                    implode(', ', self::VALID_PROGRESS_TYPES)
                )
            );
        }

        $this->settings['styling']['progress_format'][$type] = $format;
        return $this;
    }

    // =============================
    // PERFORMANCE THRESHOLD METHODS
    // =============================

    public function getPerformanceThreshold(string $level): ?int
    {
        if (!in_array($level, self::VALID_PERFORMANCE_LEVELS, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid performance level "%s". Valid levels are: %s',
                    $level,
                    implode(', ', self::VALID_PERFORMANCE_LEVELS)
                )
            );
        }

        return $this->settings['thresholds'][$level] ?? null;
    }

    public function getPerformanceThresholds(): array
    {
        return $this->settings['thresholds'];
    }

    public function setPerformanceThreshold(string $level, int $threshold): self
    {
        if (!in_array($level, self::VALID_PERFORMANCE_LEVELS, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid performance level "%s". Valid levels are: %s',
                    $level,
                    implode(', ', self::VALID_PERFORMANCE_LEVELS)
                )
            );
        }

        if ($threshold <= 0) {
            throw new InvalidArgumentException('Performance threshold must be greater than 0');
        }

        $this->settings['thresholds'][$level] = $threshold;
        return $this;
    }

    public function getPerformanceRating(int $recordsPerSecond): string
    {
        $thresholds = $this->getPerformanceThresholds();

        return match (true) {
            $recordsPerSecond >= $thresholds['excellent'] => 'excellent',
            $recordsPerSecond >= $thresholds['good'] => 'good',
            $recordsPerSecond >= $thresholds['average'] => 'average',
            $recordsPerSecond >= $thresholds['slow'] => 'slow',
            default => 'very_slow'
        };
    }

    // =============================
    // MEMORY METHODS
    // =============================

    public function areMemoryWarningsEnabled(): bool
    {
        return $this->settings['memory']['warnings_enabled'] ?? true;
    }

    public function getMemoryThreshold(string $level): ?string
    {
        if (!in_array($level, self::VALID_MEMORY_LEVELS, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid memory level "%s". Valid levels are: %s',
                    $level,
                    implode(', ', self::VALID_MEMORY_LEVELS)
                )
            );
        }

        return $this->settings['memory']['thresholds'][$level] ?? null;
    }

    public function getMemoryThresholds(): array
    {
        return $this->settings['memory']['thresholds'];
    }

    public function getPerRecordMemoryThreshold(string $level): ?int
    {
        if (!in_array($level, self::VALID_PER_RECORD_LEVELS, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid per-record level "%s". Valid levels are: %s',
                    $level,
                    implode(', ', self::VALID_PER_RECORD_LEVELS)
                )
            );
        }

        return $this->settings['memory']['per_record'][$level] ?? null;
    }

    public function getPerRecordMemoryThresholds(): array
    {
        return $this->settings['memory']['per_record'];
    }

    public function getMemoryOption(string $option): mixed
    {
        return $this->settings['memory']['options'][$option] ?? null;
    }

    public function getMemoryOptions(): array
    {
        return $this->settings['memory']['options'];
    }

    public function getMemorySettings(): array
    {
        return $this->settings['memory'];
    }

    public function setMemoryOption(string $option, mixed $value): self
    {
        $this->settings['memory']['options'][$option] = $value;
        return $this;
    }

    public function getMemoryRating(int $kbPerRecord): string
    {
        $thresholds = $this->getPerRecordMemoryThresholds();

        return match (true) {
            $kbPerRecord <= $thresholds['efficient'] => 'efficient',
            $kbPerRecord <= $thresholds['acceptable'] => 'acceptable',
            $kbPerRecord <= $thresholds['concerning'] => 'concerning',
            $kbPerRecord <= $thresholds['excessive'] => 'excessive',
            default => 'critical'
        };
    }

    // =============================
    // DEBUG METHODS
    // =============================

    public function isDebugVerbose(): bool
    {
        return $this->settings['debug']['verbose'] ?? false;
    }

    public function isLogToFileEnabled(): bool
    {
        return $this->settings['debug']['log_to_file'] ?? false;
    }

    public function getLogFilePath(): string
    {
        return $this->settings['debug']['log_file']
            ?? storage_path('logs/wimd-seeding.log');
    }

    public function getDebugSettings(): array
    {
        return $this->settings['debug'];
    }

    public function setDebugOption(string $option, mixed $value): self
    {
        $this->settings['debug'][$option] = $value;
        return $this;
    }

    // =============================
    // GENERAL METHODS
    // =============================

    public function setSetting(string $key, mixed $value): self
    {
        $this->settings[$key] = $value;
        return $this;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function getAllSettings(): array
    {
        return $this->settings;
    }

    public function reload(array $customSettings = []): self
    {
        $this->loadConfiguration($customSettings);
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function set(string $key, mixed $value): self
    {
        data_set($this->settings, $key, $value);
        return $this;
    }

    public function has(string $key): bool
    {
        return data_get($this->settings, $key) !== null;
    }

    public function toArray(): array
    {
        return $this->settings;
    }

    public function toJson(): string
    {
        return json_encode($this->settings, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    public function merge(array $settings): self
    {
        $this->settings = array_replace_recursive($this->settings, $settings);
        $this->validateConfiguration();
        return $this;
    }

    public function reset(): self
    {
        $this->settings = self::DEFAULT_CONFIG;
        return $this;
    }

    // =============================
    // CONVENIENCE METHODS
    // =============================

    public function isFullMode(): bool
    {
        return $this->isMode('full');
    }

    public function isLightMode(): bool
    {
        return $this->isMode('light');
    }

    public function enableStyling(): self
    {
        return $this->setStylingOption('use_emojis', true)
            ->setStylingOption('use_colors', true);
    }

    public function disableStyling(): self
    {
        return $this->setStylingOption('use_emojis', false)
            ->setStylingOption('use_colors', false);
    }
}
