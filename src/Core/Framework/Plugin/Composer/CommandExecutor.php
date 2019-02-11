<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Composer;

use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CommandExecutor
{
    /**
     * @var Application
     */
    private $application;

    /**
     * @var string
     */
    private $projectDir;

    public function __construct(string $projectDir)
    {
        $this->application = new Application();
        $this->application->setAutoExit(false);

        $this->projectDir = $projectDir;
    }

    public function require(string $pluginComposerName): void
    {
        $output = new BufferedOutput();

        $input = new ArrayInput(
            [
                'command' => 'require',
                'packages' => [$pluginComposerName],
                '--working-dir' => $this->projectDir,
                '--no-interaction' => null,
            ]
        );

        $exitCode = $this->application->run($input, $output);

        if ($exitCode === 0) {
            return;
        }

        // TODO NEXT-1797: Throw Exception and tell the user what happened
    }
}
