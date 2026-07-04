<?php

namespace WhileSmart\LaravelPluginEngine\Services;

/**
 * Runs Composer commands for the plugin engine. Abstracted behind an
 * interface so installs can be exercised in tests without shelling out.
 */
interface ComposerRunner
{
    /**
     * Run a Composer command in the given working directory.
     *
     * @param  array<int, string>  $command  Full command, e.g. ['composer', 'require', 'vendor/pkg:^1.0'].
     * @param  callable|null  $onOutput  Optional output handler receiving (string $type, string $buffer).
     * @return bool Whether the command succeeded.
     */
    public function run(array $command, string $workingDirectory, ?callable $onOutput = null): bool;
}
