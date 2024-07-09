<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\Coverage\Command;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration;
use Shopware\Core\DevOps\DevOps;
use Shopware\Core\DevOps\StaticAnalyze\Coverage\Command\GetClassesPerAreaCommand;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\System;
use Shopware\Elasticsearch\Elasticsearch;
use Shopware\Storefront\Storefront;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
class GetClassesPerAreaCommandTest extends TestCase
{
    use KernelTestBehaviour;

    #[Before]
    #[After]
    public function cleanUp(): void
    {
        $projectDir = $_SERVER['PROJECT_ROOT'];
        $filesystem = new Filesystem();

        $finder = new Finder();
        $phpunitFiles = $finder->in($projectDir)
            ->depth(0)
            ->files()
            ->name('phpunit.*.xml');

        $filesystem->remove($phpunitFiles);
    }

    protected function setUp(): void
    {
        $projectDir = $_SERVER['PROJECT_ROOT'];

        if (!file_exists($projectDir . '/vendor/shopware/core') || !file_exists($projectDir . '/vendor/shopware/platform')) {
            static::markTestSkipped('This test expects shopware installed over composer and does not work with the git setup');
        }
    }

    public function testGetClasses(): void
    {
        // if the test does not find any shopware classes run: composer dump-autoload -o
        $output = $this->runCommand([
            ('--' . GetClassesPerAreaCommand::OPTION_NAMESPACE_PATTERN) => GetClassesPerAreaCommand::NAMESPACE_PATTERN_DEFAULT,
            ('--' . GetClassesPerAreaCommand::OPTION_JSON) => true,
        ]);

        $result = json_decode($output, true);

        static::assertNotNull($result);

        static::assertArrayHasKey('core', $result);
        static::assertArrayHasKey(Framework::class, $result['core']);
        static::assertArrayHasKey(System::class, $result['core']);
        static::assertArrayHasKey(DevOps::class, $result['core']);

        if ($this->isBundleLoaded('Elasticsearch')) {
            static::assertArrayHasKey(Elasticsearch::class, $result['core']);
        }

        if ($this->isBundleLoaded('Administration')) {
            static::assertArrayHasKey('administration', $result);
            static::assertArrayHasKey(Administration::class, $result['administration']);
        }

        if ($this->isBundleLoaded('Storefront')) {
            static::assertArrayHasKey('storefront', $result);
            static::assertArrayHasKey(Storefront::class, $result['storefront']);
        }
    }

    public function testGeneratedPhpunitFiles(): void
    {
        $this->runCommand([
            ('--' . GetClassesPerAreaCommand::OPTION_NAMESPACE_PATTERN) => GetClassesPerAreaCommand::NAMESPACE_PATTERN_DEFAULT,
            ('--' . GetClassesPerAreaCommand::OPTION_GENERATE_PHPUNIT_TEST) => true,
        ]);

        $areas = [
            'core' => [
                Framework::class,
                System::class,
                DevOps::class,
            ],
        ];

        if ($this->isBundleLoaded('Storefront')) {
            $areas['storefront'] = [
                Storefront::class,
            ];
        }

        if ($this->isBundleLoaded('Administration')) {
            $areas['administration'] = [
                Administration::class,
            ];
        }

        if ($this->isBundleLoaded('Elasticsearch')) {
            $areas['core'][] = Elasticsearch::class;
        }

        foreach ($areas as $area => $classes) {
            $phpunitFile = $_SERVER['PROJECT_ROOT'] . '/phpunit.' . $area . '.xml';
            $coveredFiles = $this->getCoveredFiles($phpunitFile);
            foreach ($classes as $class) {
                static::assertContains((new \ReflectionClass($class))->getFileName(), $coveredFiles);
            }
        }
    }

    private function isBundleLoaded(string $bundleName): bool
    {
        $bundles = $this->getKernel()->getContainer()->getParameter('kernel.bundles');

        return (bool) ($bundles[$bundleName] ?? false);
    }

    /**
     * @param mixed[] $parameters
     */
    private function runCommand(array $parameters): string
    {
        /** @var ClassLoader $classLoader */
        $classLoader = $this->getContainer()->get('Composer\Autoload\ClassLoader');
        $tester = new CommandTester(new GetClassesPerAreaCommand($classLoader));

        $tester->execute($parameters);

        return $tester->getDisplay();
    }

    /**
     * @return string[]
     */
    private function getCoveredFiles(string $phpunitPath): array
    {
        static::assertFileExists($phpunitPath);

        $xml = simplexml_load_file($phpunitPath);
        static::assertNotFalse($xml);
        $corePhpUnit = json_decode((string) json_encode($xml), true);

        static::assertNotEmpty($corePhpUnit['source']['include']['file'] ?? []);

        return array_filter(array_map('realpath', $corePhpUnit['source']['include']['file']));
    }
}
