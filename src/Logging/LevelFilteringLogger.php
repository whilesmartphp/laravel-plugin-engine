<?php

namespace WhileSmart\LaravelPluginEngine\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * PSR-3 decorator that drops records below a minimum level before
 * they reach the underlying logger.
 */
class LevelFilteringLogger extends AbstractLogger
{
    /**
     * PSR-3 level names mapped to RFC 5424 severity (higher is more severe).
     */
    protected const SEVERITY = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    protected LoggerInterface $logger;

    protected int $minSeverity;

    public function __construct(LoggerInterface $logger, string $minLevel = LogLevel::DEBUG)
    {
        $this->logger = $logger;
        $this->minSeverity = static::SEVERITY[strtolower($minLevel)] ?? static::SEVERITY[LogLevel::DEBUG];
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Unknown levels pass through so the inner logger can decide how to handle them
        $severity = static::SEVERITY[strtolower((string) $level)] ?? PHP_INT_MAX;

        if ($severity < $this->minSeverity) {
            return;
        }

        $this->logger->log($level, $message, $context);
    }
}
