<?php

namespace WhileSmart\LaravelPluginEngine\Tests\Stubs;

use WhileSmart\LaravelPluginEngine\Services\ComposerRunner;

/**
 * Records the Composer commands an install would run, so dependency
 * installation can be asserted without shelling out to Composer.
 */
class FakeComposerRunner implements ComposerRunner
{
    /** @var array<int, array<int, string>> */
    public array $commands = [];

    public function __construct(public bool $succeeds = true) {}

    public function run(array $command, string $workingDirectory, ?callable $onOutput = null): bool
    {
        $this->commands[] = $command;

        return $this->succeeds;
    }

    /**
     * Whether any recorded command contains all the given fragments.
     */
    public function ran(string ...$fragments): bool
    {
        foreach ($this->commands as $command) {
            $haystack = implode(' ', $command);

            if (collect($fragments)->every(fn ($fragment) => str_contains($haystack, $fragment))) {
                return true;
            }
        }

        return false;
    }
}
