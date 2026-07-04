<?php

namespace WhileSmart\LaravelPluginEngine\Services;

use Symfony\Component\Process\Process;

class SymfonyComposerRunner implements ComposerRunner
{
    public function run(array $command, string $workingDirectory, ?callable $onOutput = null): bool
    {
        $process = new Process($command, $workingDirectory, null, null, 300);

        $process->run($onOutput);

        return $process->isSuccessful();
    }
}
