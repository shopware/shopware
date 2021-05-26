<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Kernel;
use Symfony\Component\Finder\Finder;

/**
 * @group slow
 */
class DeprecatedTagTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * white list file path segments for ignored paths
     *
     * @var array
     */
    private $whiteList = [
        'Test/',
        'node_modules/',
        'Common/vendor/',
        'Recovery/vendor',
        'recovery/vendor',
        'storefront/vendor',
        // we cannot remove the method, because old migrations could still use it
        'Migration/MigrationStep.php',
        // example plugin
        'deprecation.plugin.js',
    ];

    private string $rootDir;

    private string $manifestRoot;

    private ?DeprecationTagTester $deprecationTagTester = null;

    public function setUp(): void
    {
        $this->rootDir = $this->getPathForClass(Kernel::class);
        $this->manifestRoot = $this->getPathForClass(Manifest::class);

        static::markTestSkipped('This test is currently broken and fails on Github Actions. Will be fixed with NEXT-15105');
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
            ->contains('@deprecated');

        foreach ($this->whiteList as $path) {
            $finder->notPath($path);
        }

        $invalidFiles = [];

        foreach ($finder->getIterator() as $file) {
            $filePath = $file->getRealPath();
            $content = file_get_contents($filePath);

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
            ->contains('<deprecated>');

        foreach ($this->whiteList as $path) {
            $finder->notPath($path);
        }

        $invalidFiles = [];

        foreach ($finder->getIterator() as $file) {
            $filePath = $file->getRealPath();
            $content = file_get_contents($filePath);

            try {
                $this->getDeprecationTagTester()->validateTagElement($content);
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
        $path = realpath(\dirname(KernelLifecycleManager::getClassLoader()->findFile($className)) . '/../');

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
        $envVersion = $_SERVER['VERSION'] ?? '';
        if (\is_string($envVersion) && $envVersion !== '') {
            return $envVersion;
        }
        $tags = $this->exec('git tag');

        $tags = array_filter(array_map(static function ($tag): ?string {
            if (\strlen($tag) > 8) {
                return null;
            }

            return str_replace('v', '', $tag);
        }, $tags));

        return $this->getHighestVersion($tags);
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

        return $this->getHighestVersion($manifestVersions);
    }

    private function exec(string $command): array
    {
        $result = [];
        $exitCode = 0;

        exec($command, $result, $exitCode);

        if ($exitCode !== 0) {
            throw new \Exception("Could not execute {$command} successfully. EXITING \n");
        }

        return $result;
    }

    private function getHighestVersion(array $versions): string
    {
        if (empty($versions)) {
            throw new \LogicException('no version applied');
        }

        $highest = null;
        foreach ($versions as $version) {
            if ($highest === null || version_compare($highest, $version) === -1) {
                $highest = $version;
            }
        }

        return $highest;
    }
}
