<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Composer;

use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Util\ConfigValidator;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;

class PackageProvider
{
    /**
     * @throws PluginComposerJsonInvalidException
     */
    public function getPluginInformation(string $pluginPath, IOInterface $composerIO): RootPackageInterface
    {
        $composerJsonPath = $pluginPath . '/composer.json';
        $validator = new ConfigValidator($composerIO);

        [$errors, $publishErrors, $warnings] = $validator->validate($composerJsonPath);
        $errors = array_merge($errors, $publishErrors);
        if (\count($errors) !== 0) {
            throw new PluginComposerJsonInvalidException($composerJsonPath, implode("\n", $errors));
        }

        if (\count($warnings) !== 0) {
            $warningsString = implode("\n", $warnings);
            $composerIO->write(sprintf("Attention!\nThe '%s' has some warnings:\n%s", $composerJsonPath, $warningsString));
        }

        return Factory::create($composerIO, $composerJsonPath)->getPackage();
    }
}
