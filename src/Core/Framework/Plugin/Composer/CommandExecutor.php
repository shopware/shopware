<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Composer;

use Composer\Console\Application;
use Composer\InstalledVersions;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerRemoveException;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerRequireException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[Package('core')]
class CommandExecutor
{
    private readonly Application $application;

    /**
     * @internal
     */
    public function __construct(private readonly string $projectDir)
    {
        $this->application = new Application();
        $this->application->setAutoExit(false);
    }

    public function require(string $pluginComposerName, string $pluginName): void
    {
        $lockState = $this->lockComposerPackage();

        $output = new BufferedOutput();
        $input = new ArrayInput(
            [
                'command' => 'require',
                'packages' => [$pluginComposerName],
                '--working-dir' => $this->projectDir,
                '--no-interaction' => null,
                '--update-with-dependencies' => null,
                '--no-scripts' => null,
            ]
        );

        $exitCode = $this->application->run($input, $output);

        $this->unlockComposerPackage($lockState);

        if ($exitCode === 0) {
            // Composer reverts the files, when the require command fails. We don't need a reset on an error case
            $this->resetOpcache();

            return;
        }

        throw new PluginComposerRequireException($pluginName, $pluginComposerName, $output->fetch());
    }

    public function remove(string $pluginComposerName, string $pluginName): void
    {
        $output = new BufferedOutput();
        $input = new ArrayInput(
            [
                'command' => 'remove',
                'packages' => [$pluginComposerName],
                '--working-dir' => $this->projectDir,
                '--no-interaction' => null,
                '--no-scripts' => null,
            ]
        );

        $exitCode = $this->application->run($input, $output);

        if ($exitCode === 0) {
            // Composer reverts the files, when the remove command fails. We don't need a reset on an error case
            $this->resetOpcache();

            return;
        }

        throw new PluginComposerRemoveException($pluginName, $pluginComposerName, $output->fetch());
    }

    /**
     * We need to reset the opcache, when plugins are installed or updated, because the autoloader changes.
     */
    private function resetOpcache(): void
    {
        if (\function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    /**
     * We lock the composer/composer package to the current version to prevent composer from updating itself.
     */
    private function lockComposerPackage(): ?string
    {
        $composerJsonPath = $this->projectDir . '/composer.json';
        $composerJson = json_decode((string) file_get_contents($composerJsonPath), true, flags: \JSON_THROW_ON_ERROR);

        $before = $composerJson['require']['composer/composer'] ?? null;

        $composerJson['require']['composer/composer'] = InstalledVersions::getVersion('composer/composer');

        file_put_contents($composerJsonPath, json_encode($composerJson, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        return $before;
    }

    private function unlockComposerPackage(?string $beforeState): void
    {
        $composerJsonPath = $this->projectDir . '/composer.json';
        $composerJson = json_decode((string) file_get_contents($composerJsonPath), true, flags: \JSON_THROW_ON_ERROR);

        if ($beforeState !== null) {
            $composerJson['require']['composer/composer'] = $beforeState;
        } else {
            unset($composerJson['require']['composer/composer']);
        }

        file_put_contents($composerJsonPath, json_encode($composerJson, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }
}
