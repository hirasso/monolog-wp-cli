<?php

declare(strict_types=1);

/**
 * Handler for Monolog that uses WP-CLI methods to for logging.
 *
 * @link https://github.com/mhcg/monolog-wp-cli
 *
 * @author Mark Heydon <contact@mhcg.co.uk>
 * @author Rasso Hilber <mail@rassohilber.com>
 *
 */

namespace MHCG\Monolog\Handler;

use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use WP_CLI;


readonly class LoggerMapEntry
{
    public function __construct(
        public Level $level,
        public string $method,
        public bool $includeLevelName = false,
        public bool $exit = false
    ) {
        if ($level->isLowerThan(Level::Error) && $exit === true) {
            throw new \InvalidArgumentException("`exit` is only allowed for levels >= Error");
        }
    }
}

/**
 * Handler for Monolog that uses WP-CLI methods to for logging.
 *
 * @package MHCG\Monolog\Handler
 */
final class WPCLIHandler extends AbstractProcessingHandler
{
    /** Use verbose style log message format */
    private bool $verbose = false;

    /** null|LoggerMapEntry[] Logger map to use for mapping Logger methods to WP-CLI methods */
    private ?array $loggerMap = null;

    /**
     * WPCLIHandler constructor.
     */
    public function __construct(
        ?Level $level = Level::Info,
        ?bool $bubble = true,
        ?bool $verbose = false
    ) {
        if (!self::isWPCLI()) {
            throw new \RuntimeException('WPCLIHandler only works in WP_CLI');
        }

        parent::__construct($level, $bubble);

        $this->verbose = $verbose;
    }

    /**
     * Conditional to check if inside WP_CLI
     */
    public static function isWPCLI(): bool
    {
        return defined('WP_CLI') && WP_CLI;
    }

    /**
     * @inheritDoc
     */
    public function isHandling(LogRecord $record): bool
    {
        // First check if this record should be handled at all
        if (!parent::isHandling($record)) return false;

        // WP_CLI deals with --debug command argument
        if ($record->level === Level::Debug) {
            return true;
        }

        return !!$this->getLoggerMapEntry($record->level);
    }

    /**
     * Check if a provided log record's level matches on supported by us
     */
    public function getLoggerMapEntry(Level $level): ?LoggerMapEntry
    {
        foreach ($this->getLoggerMap() as $entry) {
            if ($entry->level === $level) return $entry;
        }
        return null;
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @see https://seldaek.github.io/monolog/doc/message-structure.html
     */
    protected function write(LogRecord $record): void
    {
        // Bail early if the current record's log level doesn't match any of ours
        if (!$mapEntry = $this->getLoggerMapEntry($record->level)) {
            return;
        }

        $message = $this->getFormatter()->format($record);
        $method = $mapEntry->method;

        if ($mapEntry->includeLevelName) {
            $message = "({$mapEntry->level->name}) $message";
        }

        if ($method !== 'error') {
            WP_CLI::$method($message);
            return;
        }

        WP_CLI::$method($message, $mapEntry->exit);
    }

    /**
     * Returns the Logger map.
     *
     * @return LoggerMapEntry[]
     */
    public function getLoggerMap()
    {
        return [
            new LoggerMapEntry(
                level: Level::Debug,
                method: 'debug',
            ),
            new LoggerMapEntry(
                level: Level::Info,
                method: 'log',
            ),
            new LoggerMapEntry(
                level: Level::Notice,
                method: 'log',
            ),
            new LoggerMapEntry(
                level: Level::Warning,
                method: 'warning',
            ),
            new LoggerMapEntry(
                level: Level::Error,
                method: 'error',
                includeLevelName: true,
                exit: false,
            ),
            new LoggerMapEntry(
                level: Level::Critical,
                method: 'error',
                includeLevelName: true,
                exit: true,
            ),
            new LoggerMapEntry(
                level: Level::Alert,
                method: 'error',
                includeLevelName: true,
                exit: true,
            ),
            new LoggerMapEntry(
                level: Level::Emergency,
                method: 'error',
                includeLevelName: true,
                exit: true,
            )
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        if ($this->verbose) {
            return new LineFormatter("%message% %context% %extra%");
        }

        return new LineFormatter("%message%");
    }
}
