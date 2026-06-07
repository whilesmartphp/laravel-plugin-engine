<?php

namespace WhileSmart\LaravelPluginEngine\Tests\Stubs\Logging;

use Psr\Log\AbstractLogger;

class SpyLogger extends AbstractLogger
{
    /** @var array<int, array{level: string, message: string, context: array}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * Get the recorded levels.
     */
    public function levels(): array
    {
        return array_column($this->records, 'level');
    }
}
