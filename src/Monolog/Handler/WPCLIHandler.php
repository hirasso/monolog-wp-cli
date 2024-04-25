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

use Symfony\Component\VarDumper\Caster\ScalarStub;
use Symfony\Component\VarDumper\VarDumper;


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

        // Print the message to the console
        if ($method === 'error') {
            WP_CLI::error($message, false);
        } else {
            WP_CLI::$method($message);
        }

        // If there is a context available, pretty-print it using symphony/var-dumper
        if (!empty($record->context)) {
            $this->dump($record->context);
        }

        // If the script should exit, do it.
        if ($mapEntry->exit) {
            exit;
        }
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

    /**
     * @author Nicolas Grekas <p@tchwork.com>
     * @author Alexandre Daubois <alex.daubois@gmail.com>
     */
    private function dump(mixed ...$vars): mixed
    {
        if (!$vars) {
            VarDumper::dump(new ScalarStub('🐛'));

            return null;
        }

        if (array_key_exists(0, $vars) && 1 === count($vars)) {
            VarDumper::dump($vars[0]);
            $k = 0;
        } else {
            foreach ($vars as $k => $v) {
                VarDumper::dump($v, is_int($k) ? 1 + $k : $k);
            }
        }

        if (1 < count($vars)) {
            return $vars;
        }

        return $vars[$k];
    }
}
