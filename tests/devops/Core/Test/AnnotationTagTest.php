<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\Test;

use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Test\AnnotationTagTester;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Kernel;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('core')]
#[CoversNothing]
class AnnotationTagTest extends TestCase
{
    /**
     * white list file path segments for ignored paths
     *
     * @var array<string>
     */
    private array $whiteList = [
        'vendor',
        'node_modules/',
        'Common/vendor/',
        'Recovery/vendor',
        'Core/DevOps/StaticAnalyze',
        'recovery/vendor',
        'storefront/vendor',
        // no need to check external js added as assets
        'storefront/dist/assets/js',
        // we cannot remove the method, because old migrations could still use it
        'Migration/MigrationStep.php',
        // example plugin
        'deprecation.plugin.js',
        // some eslint rules check for @deprecated and therefore produce false positives
        'administration/eslint-rules',
        // checks for deprecations too and annotation fails
        'DataAbstractionLayer/DefinitionValidator.php',
        // Annotation tags of course use @deprecated string a lot
        'Test/AnnotationTagTest.php',
        'Test/AnnotationTagTester.php',
        'Test/AnnotationTagTesterTest.php',
        // uses @experimental annotation check
        'Core/ApiRoutesHaveASchemaTest.php',
    ];

    private string $rootDir;

    private string $manifestRoot;

    private ?AnnotationTagTester $deprecationTagTester = null;

    protected function setUp(): void
    {
        $this->rootDir = $this->getPathForClass(Kernel::class);
        $this->manifestRoot = $this->getPathForClass(Manifest::class);
    }

    public function testSourceFilesForWrongDeprecatedAnnotations(): void
    {
        $finder = new Finder();
        $finder->in([$this->rootDir, $this->rootDir . '/../tests'])
            ->files()
            ->name('*.php')
            ->name('*.js')
            ->name('*.ts')
            ->name('*.scss')
            ->name('*.html.twig')
            ->name('*.xsd')
            ->exclude('node_modules')
            ->contains(['@deprecated', '@experimental']);

        foreach ($this->whiteList as $path) {
            $finder->notPath($path);
        }

        $invalidFiles = [];

        foreach ($finder->getIterator() as $file) {
            $filePath = $file->getRealPath();
            $content = (string) file_get_contents($filePath);

            try {
                $this->getDeprecationTagTester()->validateDeprecatedAnnotations($content);
                $this->getDeprecationTagTester()->validateExperimentalAnnotations($content);
            } catch (\InvalidArgumentException $error) {
                $area = $this->getAreaForContent($content);
                $invalidFiles[$area ?? 'undefined'][$filePath] = $error->getMessage();
            }
        }

        static::assertEmpty($invalidFiles, print_r($invalidFiles, true));
    }

    public function testConfigFilesForWrongDeprecatedTags(): void
    {
        $finder = new Finder();
        $finder->in([$this->rootDir, $this->rootDir . '/../tests'])
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
                if ($error->getMessage() !== 'Deprecation tag is not found in the file.') {
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

    private function getDeprecationTagTester(): AnnotationTagTester
    {
        if ($this->deprecationTagTester === null) {
            $this->deprecationTagTester = new AnnotationTagTester(
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
            $manifestVersions[] = AnnotationTagTester::getVersionFromManifestFileName($file->getFilename());
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
                \sprintf(
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

    private function getAreaForContent(string $content): ?string
    {
        $matches = [];
        preg_match("/#\[Package\('(?<area>.*)'\)]/", $content, $matches);
        if (isset($matches['area'])) {
            return $matches['area'];
        }

        $matches = [];
        preg_match("/@package\s*(?<area>\S*)/", $content, $matches);
        if (isset($matches['area'])) {
            return $matches['area'];
        }

        return null;
    }
}
