<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Snippet;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Test\Adapter\Twig\fixtures\BundleFixture;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Kernel;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
class SnippetFinderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SnippetFinder $snippetFinder;

    protected function setUp(): void
    {
        $this->snippetFinder = new SnippetFinder(
            $this->getKernel(),
            $this->getContainer()->get(Connection::class)
        );
    }

    public function testGetPluginPath(): void
    {
        $kernelMock = $this->createMock(Kernel::class);

        $loader = $this->createMock(ClassLoader::class);
        $loader->method('findFile')->willReturn(__DIR__);

        $kernelPluginLoader = new StaticKernelPluginLoader(
            $loader,
            null,
            [
                [
                    'name' => 'FakePlugin',
                    'active' => true,
                    'baseClass' => FakePlugin::class,
                    'path' => 'src/FakePlugin',
                    'autoload' => [
                        'psr-4' => [
                            'Shopware\\FakePlugin\\' => 'src/',
                        ],
                    ],
                    'managedByComposer' => true,
                ],
            ]
        );

        $kernelPluginLoader->initializePlugins($this->getContainer()->getParameter('kernel.project_dir'));

        $kernelMock
            ->expects(static::exactly(2))
            ->method('getPluginLoader')
            ->willReturn($kernelPluginLoader);

        $kernelMock
            ->expects(static::exactly(1))
            ->method('getBundles')
            ->willReturn([new BundleFixture('SomeBundle', __DIR__ . '/fixtures/caseBundleLoading/bundle')]);

        $this->snippetFinder = new SnippetFinder(
            $kernelMock,
            $this->getContainer()->get(Connection::class)
        );

        $reflectionClass = new \ReflectionClass(SnippetFinder::class);
        $reflectionMethod = $reflectionClass->getMethod('getPluginPaths');
        $reflectionMethod->setAccessible(true);
        $returnValue = $reflectionMethod->invoke($this->snippetFinder);

        static::assertCount(2, $returnValue);
        static::assertContains(__DIR__ . '/fixtures/caseBundleLoadingWithPlugin/bundle/Resources/app/administration', $returnValue);
        static::assertContains(__DIR__ . '/fixtures/caseBundleLoading/bundle/Resources/app/administration', $returnValue);
    }

    public function testGetPluginPathWithDuplicatePlugin(): void
    {
        $kernelMock = $this->createMock(Kernel::class);
        $pluginMock = $this->createMock(Plugin::class);
        $activeReflection = new \ReflectionProperty(Plugin::class, 'active');
        $activeReflection->setAccessible(true);
        $activeReflection->setValue($pluginMock, true);
        $pluginMock->method('getPath')->willReturn(__DIR__ . '/fixtures/caseBundleLoadingWithPlugin/bundle');

        $kernelPluginLoaderMock = $this->createMock(KernelPluginLoader::class);
        $activeReflection = new \ReflectionProperty(KernelPluginLoader::class, 'pluginInstances');
        $activeReflection->setAccessible(true);
        $activeReflection->setValue($kernelPluginLoaderMock, new KernelPluginCollection([$pluginMock]));

        $kernelMock
            ->expects(static::exactly(2))
            ->method('getPluginLoader')
            ->willReturn($kernelPluginLoaderMock);

        $kernelMock
            ->expects(static::exactly(1))
            ->method('getBundles')
            ->willReturn([$pluginMock]);

        $this->snippetFinder = new SnippetFinder(
            $kernelMock,
            $this->getContainer()->get(Connection::class)
        );

        $reflectionClass = new \ReflectionClass(SnippetFinder::class);
        $reflectionMethod = $reflectionClass->getMethod('getPluginPaths');
        $reflectionMethod->setAccessible(true);
        $returnValue = $reflectionMethod->invoke($this->snippetFinder);

        static::assertCount(1, $returnValue);
        static::assertContains(__DIR__ . '/fixtures/caseBundleLoadingWithPlugin/bundle/Resources/app/administration', $returnValue);
    }

    public function testValidSnippetMergeWithOnlySameLanguageFiles(): void
    {
        $actual = $this->getResultSnippetsByCase('caseSameLanguage', 'de-DE');

        $expected = [
            'test' => [
                'uniqueNamespaceCore' => [
                    'someLabel' => 'core',
                    'anotherLabel' => 'core',
                ],
                'uniqueNamespacePlugin' => [
                    'someLabel' => 'plugin',
                    'anotherLabel' => 'plugin',
                ],
                'someSharedNamespace' => [
                    'uniqueKeyCore' => 'core',
                    'uniqueKeyPlugin' => 'plugin',
                    'shouldBeOverwritten' => 'overwritten by plugin',
                    'shouldAlsoBeOverwritten' => 'also overwritten by plugin',
                ],
            ],
        ];

        static::assertEquals($expected, $actual);
    }

    public function testValidSnippetMergeWithDifferentLanguageFiles(): void
    {
        $actual = $this->getResultSnippetsByCase('caseDifferentLanguages', 'de-DE');

        $expected = [
            'test' => [
                'uniqueNamespaceCore' => [
                    'someLabel' => 'core',
                    'anotherLabel' => 'core',
                ],
                'someSharedNamespace' => [
                    'uniqueKeyCore' => 'core',
                    'shouldBeOverwritten' => 'This time no override',
                    'shouldAlsoBeOverwritten' => 'This time no override either',
                ],
            ],
        ];

        static::assertEquals($expected, $actual);
    }

    public function testValidSnippetMergeWithMultipleLanguageFiles(): void
    {
        $actualDe = $this->getResultSnippetsByCase('caseMultipleSameAndDifferentLanguages', 'de-DE');
        $actualEn = $this->getResultSnippetsByCase('caseMultipleSameAndDifferentLanguages', 'en-GB');

        $expectedDe = [
            'test' => [
                'uniqueNamespaceCore' => [
                    'someLabel' => 'core de',
                    'anotherLabel' => 'core de',
                ],
                'uniqueNamespacePlugin' => [
                    'someLabel' => 'plugin de',
                    'anotherLabel' => 'plugin de',
                ],
                'someSharedNamespace' => [
                    'uniqueKeyCore' => 'core de',
                    'uniqueKeyPlugin' => 'plugin de',
                    'shouldBeOverwritten' => 'overwritten by plugin de',
                    'shouldAlsoBeOverwritten' => 'also overwritten by plugin de',
                ],
            ],
        ];

        $expectedEn = [
            'test' => [
                'uniqueNamespaceCore' => [
                    'someLabel' => 'core en',
                    'anotherLabel' => 'core en',
                ],
                'uniqueNamespacePlugin' => [
                    'someLabel' => 'plugin en',
                    'anotherLabel' => 'plugin en',
                ],
                'someSharedNamespace' => [
                    'uniqueKeyCore' => 'core en',
                    'uniqueKeyPlugin' => 'plugin en',
                    'shouldBeOverwritten' => 'overwritten by plugin en',
                    'shouldAlsoBeOverwritten' => 'also overwritten by plugin en',
                ],
            ],
        ];

        static::assertEquals($expectedDe, $actualDe);
        static::assertEquals($expectedEn, $actualEn);
    }

    /**
     * @return array<string>
     */
    private function getSnippetFilePathsOfFixtures(string $folder, string $namePattern): array
    {
        $finder = (new Finder())
            ->files()
            ->in(__DIR__ . '/fixtures/' . $folder . '/')
            ->ignoreUnreadableDirs()
            ->name($namePattern);

        $iterator = $finder->getIterator();

        $files = [];
        foreach ($iterator as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function getResultSnippetsByCase(string $folder, string $locale): array
    {
        $files = $this->getSnippetFilePathsOfFixtures($folder, '/' . $locale . '.json/');
        $files = $this->ensureFileOrder($files);

        $reflectionClass = new \ReflectionClass(SnippetFinder::class);
        $reflectionMethod = $reflectionClass->getMethod('parseFiles');
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invoke(
            $this->snippetFinder,
            $files
        );
    }

    /**
     * @param array<int, string> $files
     *
     * @return array<int, string>
     */
    private function ensureFileOrder(array $files): array
    {
        // core should be overwritten by plugin fixture, therefore core should be index 0
        if (mb_strpos($files[0], '/core/') === false) {
            foreach ($files as $currentIndex => $file) {
                if (mb_strpos($file, '/core/') !== false) {
                    [$files[0], $files[$currentIndex]] = [$files[$currentIndex], $files[0]];

                    return $files;
                }
            }
        }

        return $files;
    }
}

/**
 * @internal
 */
class FakePlugin extends Plugin
{
    public function getPath(): string
    {
        return __DIR__ . '/fixtures/caseBundleLoadingWithPlugin/bundle';
    }
}
