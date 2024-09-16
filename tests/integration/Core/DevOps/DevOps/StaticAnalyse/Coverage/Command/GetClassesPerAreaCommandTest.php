<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\DevOps\DevOps\StaticAnalyse\Coverage\Command;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration;
use Shopware\Core\DevOps\DevOps;
use Shopware\Core\DevOps\StaticAnalyze\Coverage\Command\GetClassesPerAreaCommand;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\System\System;
use Shopware\Elasticsearch\Elasticsearch;
use Shopware\Storefront\Storefront;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
class GetClassesPerAreaCommandTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @before
     *
     * @after
     */
    public function cleanUp(): void
    {
        $filesystem = new Filesystem();
        $projectDir = $this->getProjectDir();

        $finder = new Finder();
        $phpunitFiles = $finder->in($projectDir)
            ->depth(0)
            ->files()
            ->name('phpunit.*.xml');

        $filesystem->remove($phpunitFiles);
    }

    public function testGetClasses(): void
    {
        static::markTestSkipped('broken');

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
        static::markTestSkipped('broken');

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
            $phpunitFile = $this->getProjectDir() . '/phpunit.' . $area . '.xml';
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
        $projectDir = $this->getProjectDir();

        $getClassesCommand = new GetClassesPerAreaCommand($projectDir);
        $definition = $getClassesCommand->getDefinition();
        $input = new ArrayInput(
            $parameters,
            $definition
        );
        $input->getOptions();
        $output = new BufferedOutput();

        $refMethod = ReflectionHelper::getMethod(GetClassesPerAreaCommand::class, 'execute');
        $refMethod->invoke($getClassesCommand, $input, $output);

        return $output->fetch();
    }

    private function getProjectDir(): string
    {
        $vendorDir = key(ClassLoader::getRegisteredLoaders());
        static::assertIsString($vendorDir);

        return \dirname($vendorDir);
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

        static::assertNotEmpty($corePhpUnit['coverage']['include']['file'] ?? []);

        return array_filter(array_map('realpath', $corePhpUnit['coverage']['include']['file']));
    }
}
