<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Kernel;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('core')]
class DeprecatedTagTest extends TestCase
{
    /**
     * white list file path segments for ignored paths
     *
     * @var array<string>
     */
    private array $whiteList = [
        'vendor',
        'Test/',
        'node_modules/',
        'Common/vendor/',
        'Recovery/vendor',
        'Core/DevOps/StaticAnalyze',
        'recovery/vendor',
        'storefront/vendor',
        // we cannot remove the method, because old migrations could still use it
        'Migration/MigrationStep.php',
        // example plugin
        'deprecation.plugin.js',
        // waiting for symfony 6
        'Framework/Csrf/SessionProvider.php',
        // some eslint rules check for @deprecated and therefore produce false positives
        'administration/eslint-rules',
        // checks for deprecations too and annotation fails
        'DataAbstractionLayer/DefinitionValidator.php',
    ];

    private string $rootDir;

    private string $manifestRoot;

    private ?DeprecationTagTester $deprecationTagTester = null;

    protected function setUp(): void
    {
        $this->rootDir = $this->getPathForClass(Kernel::class);
        $this->manifestRoot = $this->getPathForClass(Manifest::class);
    }

    public function testSourceFilesForWrongDeprecatedAnnotations(): void
    {
        $finder = new Finder();
        $finder->in($this->rootDir)
            ->files()
            ->name('*.php')
            ->name('*.js')
            ->name('*.scss')
            ->name('*.html.twig')
            ->name('*.xsd')
            ->exclude('node_modules')
            ->contains('@deprecated');

        foreach ($this->whiteList as $path) {
            $finder->notPath($path);
        }

        $invalidFiles = [];

        foreach ($finder->getIterator() as $file) {
            $filePath = $file->getRealPath();
            $content = (string) file_get_contents($filePath);

            try {
                $this->getDeprecationTagTester()->validateAnnotations($content);
            } catch (\Throwable $error) {
                if (!$error instanceof NoDeprecationFoundException) {
                    $invalidFiles[$filePath] = $error->getMessage();
                }
            }
        }

        static::assertEmpty($invalidFiles, print_r($invalidFiles, true));
    }

    public function testConfigFilesForWrongDeprecatedTags(): void
    {
        $finder = new Finder();
        $finder->in($this->rootDir)
            ->files()
            ->name('*.xml')
            ->exclude('node_modules')
            ->contains('<deprecated>');

        foreach ($this->whiteList as $path) {
            $finder->notPath($path);
        }

        $invalidFiles = [];

        foreach ($finder->getIterator() as $file) {
            $filePath = $file->getRealPath();
            $content = (string) file_get_contents($filePath);

            try {
                $this->getDeprecationTagTester()->validateDeprecationElements($content);
            } catch (\Throwable $error) {
                if (!$error instanceof NoDeprecationFoundException) {
                    $invalidFiles[$filePath] = $error->getMessage();
                }
            }
        }

        static::assertEmpty($invalidFiles, print_r($invalidFiles, true));
    }

    private function getPathForClass(string $className): string
    {
        $path = realpath(\dirname((string) KernelLifecycleManager::getClassLoader()->findFile($className)) . '/../');

        if ($path === false) {
            throw new \LogicException("could not locate filepath for class {$className}");
        }

        return $path;
    }

    private function getDeprecationTagTester(): DeprecationTagTester
    {
        if ($this->deprecationTagTester === null) {
            $this->deprecationTagTester = new DeprecationTagTester(
                $this->getShopwareVersion(),
                $this->getManifestVersion()
            );
        }

        return $this->deprecationTagTester;
    }

    /**
     * can be overwritten with env variable VERSION
     */
    private function getShopwareVersion(): string
    {
        $envVersion = $_SERVER['VERSION'] ?? $_SERVER['TAG'] ?? '';
        if (\is_string($envVersion) && $envVersion !== '') {
            $shopwareVersion = $envVersion;
        } elseif (InstalledVersions::isInstalled('shopware/platform')) {
            $shopwareVersion = InstalledVersions::getVersion('shopware/platform');
        } else {
            $shopwareVersion = InstalledVersions::getVersion('shopware/core');
        }
        $shopwareVersion = ltrim((string) $shopwareVersion, 'v ');

        if (!preg_match('/^\d+\.\d+[.-].*$/', $shopwareVersion)) {
            // this will only check the syntax of the deprecated tags. The real test happens in the prod pipeline

            $matches = [];
            preg_match('/(\d+\.\d+)\..*/', Kernel::SHOPWARE_FALLBACK_VERSION, $matches);
            static::assertArrayHasKey(1, $matches);

            // get major version from Kernel::SHOPWARE_FALLBACK_VERSION
            $shopwareVersion = $matches[1] . '.0';
        }

        return $shopwareVersion;
    }

    private function getManifestVersion(): string
    {
        $finder = new Finder();
        $finder->in($this->manifestRoot)
            ->path('/Schema');

        $manifestVersions = [];
        foreach ($finder->getIterator() as $file) {
            $manifestVersions[] = DeprecationTagTester::getVersionFromManifestFileName($file->getFilename());
        }

        return $this->getCurrentManifestVersion($manifestVersions);
    }

    /**
     * @param array<string|null> $versions
     */
    private function getCurrentManifestVersion(array $versions): string
    {
        $versions = array_filter($versions);
        if (empty($versions)) {
            throw new \LogicException('no version applied');
        }

        if (\count($versions) > 2) {
            throw new \LogicException(
                sprintf(
                    'There should only be one live and one deprecated version at the same time. Found Manifest schema versions: %s',
                    print_r($versions, true)
                )
            );
        }

        $highest = null;
        foreach ($versions as $version) {
            // we have to search for the lowest version here
            // because this is the already deprecated but still used version of the manifest schema
            if ($highest === null || version_compare($highest, $version) === 1) {
                $highest = $version;
            }
        }

        return $highest;
    }
}
