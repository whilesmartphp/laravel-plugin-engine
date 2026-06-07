<?php

namespace WhileSmart\LaravelPluginEngine\Tests\Feature\Logging;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use WhileSmart\LaravelPluginEngine\Logging\LevelFilteringLogger;
use WhileSmart\LaravelPluginEngine\Tests\Stubs\Logging\SpyLogger;

class LevelFilteringLoggerTest extends TestCase
{
    /** @test */
    public function it_drops_records_below_the_minimum_level()
    {
        $spy = new SpyLogger;
        $logger = new LevelFilteringLogger($spy, LogLevel::WARNING);

        $logger->debug('debug message');
        $logger->info('info message');
        $logger->notice('notice message');

        $this->assertSame([], $spy->records);
    }

    /** @test */
    public function it_passes_records_at_or_above_the_minimum_level()
    {
        $spy = new SpyLogger;
        $logger = new LevelFilteringLogger($spy, LogLevel::WARNING);

        $logger->warning('warning message');
        $logger->error('error message', ['key' => 'value']);

        $this->assertSame([LogLevel::WARNING, LogLevel::ERROR], $spy->levels());
        $this->assertSame(['key' => 'value'], $spy->records[1]['context']);
    }

    /** @test */
    public function it_passes_everything_by_default()
    {
        $spy = new SpyLogger;
        $logger = new LevelFilteringLogger($spy);

        $logger->debug('debug message');
        $logger->emergency('emergency message');

        $this->assertSame([LogLevel::DEBUG, LogLevel::EMERGENCY], $spy->levels());
    }

    /** @test */
    public function it_passes_unknown_levels_through()
    {
        $spy = new SpyLogger;
        $logger = new LevelFilteringLogger($spy, LogLevel::ERROR);

        $logger->log('custom', 'custom level message');

        $this->assertSame(['custom'], $spy->levels());
    }

    /** @test */
    public function it_treats_an_unknown_minimum_level_as_debug()
    {
        $spy = new SpyLogger;
        $logger = new LevelFilteringLogger($spy, 'nonsense');

        $logger->debug('debug message');

        $this->assertSame([LogLevel::DEBUG], $spy->levels());
    }
}
