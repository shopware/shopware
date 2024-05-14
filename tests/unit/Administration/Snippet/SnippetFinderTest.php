<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration;
use Shopware\Administration\Snippet\SnippetException;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Storefront\Storefront;

/**
 * @internal
 */
#[CoversClass(SnippetFinder::class)]
class SnippetFinderTest extends TestCase
{
    public function testFindSnippetsFromAppNoSnippetsAdded(): void
    {
        $snippetFinder = new SnippetFinder(
            $this->getKernelMock(),
            $this->getConnectionMock('en-GB', [])
        );

        $snippets = $snippetFinder->findSnippets('en-GB');
        static::assertArrayNotHasKey('my-custom-snippet-key', $snippets);
    }

    public function testFindSnippetsFromApp(): void
    {
        $snippetFinder = new SnippetFinder(
            $this->getKernelMock(),
            $this->getConnectionMock('en-GB', $this->getSnippetFixtures())
        );

        $snippets = $snippetFinder->findSnippets('en-GB');

        $expectedSnippets = $this->getSnippetFixtures();
        $key = array_key_first($expectedSnippets);
        static::assertEquals($expectedSnippets[$key], $snippets[$key]);
    }

    public function testNoSnippetsFound(): void
    {
        $snippetFinder = new SnippetFinder(
            $this->getKernelMock(),
            $this->getConnectionMock('fr-FR', [])
        );

        static::assertEmpty($snippetFinder->findSnippets('fr-FR'));
    }

    public function testDefaultSnippetFileLoading(): void
    {
        $activePluginPaths = [
            'activePlugin',
            'invalidPlugin',
            'nonExistingPlugin',
        ];
        $pluginPaths = [
            'activePlugin',
            'irrelevantPlugin',
        ];
        $bundlePaths = [
            'existingBundle',
            'nonExistingBundle',
        ];

        $snippetFinder = new SnippetFinder(
            $this->getKernelMock($pluginPaths, $activePluginPaths, $bundlePaths),
            $this->getConnectionMock('jp-JP', [])
        );

        $actualSnippets = $snippetFinder->findSnippets('jp-JP');

        static::assertEquals([
            'activePlugin' => 'successfully loaded',
            'existingBundle' => 'successfully loaded as well',
        ], $actualSnippets);
    }

    /**
     * @param array<string, mixed> $appSnippets
     */
    #[DataProvider('validAppSnippetsDataProvider')]
    public function testValidateValidSnippets(array $appSnippets): void
    {
        $snippetFinder = new SnippetFinder(
            $this->getKernelMock(),
            $this->getConnectionMock('en-GB', $appSnippets)
        );

        $actualSnippetKeys = $snippetFinder->findSnippets('en-GB');
        foreach ($appSnippets as $key => $value) {
            static::assertArrayHasKey($key, $actualSnippetKeys);
        }
    }

    /**
     * @param array<string, mixed> $appSnippets
     * @param list<string> $duplicateSnippetKeys
     */
    #[DataProvider('invalidAppSnippetsDataProvider')]
    public function testValidateInvalidSnippets(array $appSnippets, array $duplicateSnippetKeys): void
    {
        $expectedExceptionMessage = 'The following keys on the first level are duplicated and can not be overwritten: ' . implode(', ', $duplicateSnippetKeys);

        $snippetFinder = new SnippetFinder(
            $this->getKernelMock(),
            $this->getConnectionMock('en-GB', $appSnippets)
        );

        $this->expectException(SnippetException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $snippetFinder->findSnippets('en-GB');
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    #[DataProvider('sanitizeAppSnippetDataProvider')]
    public function testSanitizeAppSnippets(array $before, array $after): void
    {
        $snippetFinder = new SnippetFinder(
            $this->getKernelMock(),
            $this->getConnectionMock('en-GB', $before)
        );

        $result = $snippetFinder->findSnippets('en-GB');
        $result = array_intersect_key($result, $before); // filter out all others snippets

        static::assertEquals($after, $result);
    }

    /**
     * @return array<string, array{appSnippets: array<string, mixed>}>
     */
    public static function validAppSnippetsDataProvider(): iterable
    {
        yield 'Everything is valid with no illegal intersections' => [
            'appSnippets' => [
                'sw-unique-app-key' => [],
            ],
        ];

        /** @var array<string, mixed> $allowedIntersectingFirstLevelSnippets */
        $allowedIntersectingFirstLevelSnippets = array_reduce(
            SnippetFinder::ALLOWED_INTERSECTING_FIRST_LEVEL_SNIPPET_KEYS,
            static function ($accumulator, $value) {
                $accumulator[$value] = [];

                return $accumulator;
            }
        );

        yield 'Everything is valid with only allowed duplicates' => [
            'appSnippets' => [
                ...$allowedIntersectingFirstLevelSnippets,
                'sw-unique-app-key' => [],
            ],
        ];
    }

    /**
     * @return array<string, array{appSnippets: array<string, mixed>, duplicateSnippetKeys: list<string>}>
     */
    public static function invalidAppSnippetsDataProvider(): iterable
    {
        yield 'Throw exception if existing snippets will be overwritten' => [
            'appSnippets' => [
                'sw-category' => [],
                'sw-cms' => [],
                'sw-wizard' => [],
            ],
            'duplicateSnippetKeys' => [
                'sw-category',
                'sw-cms',
                'sw-wizard',
            ],
        ];

        yield 'Throw exception if existing snippets contain legal and illegal duplicates' => [
            'appSnippets' => [
                ...array_flip(SnippetFinder::ALLOWED_INTERSECTING_FIRST_LEVEL_SNIPPET_KEYS),
                'sw-category' => [],
                'sw-cms' => [],
                'sw-wizard' => [],
            ],
            'duplicateSnippetKeys' => [
                'sw-category',
                'sw-cms',
                'sw-wizard',
            ],
        ];
    }

    /**
     * @return array<string, array{before: array<string, mixed>, after: array<string, mixed>}>
     */
    public static function sanitizeAppSnippetDataProvider(): iterable
    {
        yield 'Test it sanitises app snippets' => [
            'before' => [
                'foo' => [
                    'bar' => [
                        'bar' => '<h1>value</h1>',
                    ],
                ],
            ],
            'after' => [
                'foo' => [
                    'bar' => [
                        'bar' => 'value',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $snippets
     */
    private function getConnectionMock(string $expectedLocale, array $snippets): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);

        $returns = [];
        foreach ($snippets as $key => $value) {
            $returns[]['value'] = json_encode([$key => $value], \JSON_THROW_ON_ERROR);
        }

        $connection
            ->method('fetchAllAssociative')
            ->with(
                'SELECT app_administration_snippet.value
             FROM locale
             INNER JOIN app_administration_snippet ON locale.id = app_administration_snippet.locale_id
             INNER JOIN app ON app_administration_snippet.app_id = app.id
             WHERE locale.code = :code AND app.active = 1;',
                ['code' => $expectedLocale]
            )
            ->willReturn($returns);

        return $connection;
    }

    /**
     * @param list<string> $pluginPaths
     * @param list<string> $activePluginPaths
     * @param list<string> $bundlePaths
     */
    public function getKernelMock(
        array $pluginPaths = [],
        array $activePluginPaths = [],
        array $bundlePaths = []
    ): Kernel&MockObject {
        $getBundleMockByPath = function (string $path): Plugin&MockObject {
            $plugin = $this->createMock(Plugin::class);
            $plugin
                ->method('getPath')
                ->willReturn(__DIR__ . '/fixtures/' . $path);

            return $plugin;
        };

        $plugins = array_map($getBundleMockByPath, $pluginPaths);
        $activePlugins = array_map($getBundleMockByPath, $activePluginPaths);

        $adminBundle = $this->createMock(Bundle::class);
        $adminBundle
            ->method('getPath')
            ->willReturn(\dirname((string) (new \ReflectionClass(Administration::class))->getFileName()));

        $storefrontBundle = $this->createMock(Bundle::class);
        $storefrontBundle
            ->method('getPath')
            ->willReturn(\dirname((string) (new \ReflectionClass(Storefront::class))->getFileName()));

        $bundles = [
            ...array_map($getBundleMockByPath, $bundlePaths),
            ...$plugins,
            $adminBundle,
            $storefrontBundle,
        ];

        $pluginCollectionMock = $this->createMock(KernelPluginCollection::class);
        $pluginCollectionMock
            ->method('all')
            ->willReturn($plugins);
        $pluginCollectionMock
            ->method('getActives')
            ->willReturn($activePlugins);

        $pluginLoaderMock = $this->createMock(KernelPluginLoader::class);
        $pluginLoaderMock
            ->method('getPluginInstances')
            ->willReturn($pluginCollectionMock);

        $kernelMock = $this->createMock(Kernel::class);
        $kernelMock
            ->method('getPluginLoader')
            ->willReturn($pluginLoaderMock);
        $kernelMock
            ->method('getBundles')
            ->willReturn($bundles);

        return $kernelMock;
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function getSnippetFixtures(): array
    {
        return [
            'my-custom-snippet-key' => [
                'foo' => [
                    'bar' => 'baz',
                ],
            ],
        ];
    }
}
