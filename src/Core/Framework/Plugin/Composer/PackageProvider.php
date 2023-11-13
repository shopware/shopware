<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Composer;

use Composer\IO\IOInterface;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Loader\ValidatingArrayLoader;
use Composer\Util\ConfigValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;

#[Package('core')]
class PackageProvider
{
    /**
     * @throws PluginComposerJsonInvalidException
     */
    public function getPluginComposerPackage(string $pluginPath, IOInterface $composerIO): CompletePackageInterface
    {
        $composerJsonPath = $pluginPath . '/composer.json';
        $validator = new ConfigValidator($composerIO);

        [$errors, $publishErrors, $warnings] = $validator->validate($composerJsonPath, ValidatingArrayLoader::CHECK_ALL, 0);
        $errors = [...$errors, ...$publishErrors];
        if (\count($errors) !== 0) {
            throw new PluginComposerJsonInvalidException($composerJsonPath, $errors);
        }

        if (\count($warnings) !== 0) {
            $warningsString = implode("\n", $warnings);
            $composerIO->write(sprintf("Attention!\nThe '%s' has some warnings:\n%s", $composerJsonPath, $warningsString));
        }

        try {
            return Factory::createComposer($pluginPath, $composerIO)->getPackage();
        } catch (\InvalidArgumentException $e) {
            throw new PluginComposerJsonInvalidException($pluginPath . '/composer.json', [$e->getMessage()]);
        }
    }
}
