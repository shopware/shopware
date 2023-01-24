<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\SnippetException;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Kernel;

/**
 * @internal
 *
 * @covers \Shopware\Administration\Snippet\SnippetFinder
 */
class SnippetFinderTest extends TestCase
{
    /**
     * @dataProvider findSnippetsFromAppDataProvider
     *
     * @param array<string, mixed> $expectedSnippets
     */
    public function testFindSnippetsFromApp(Kernel $kernel, array $expectedSnippets, Connection $connection): void
    {
        $snippetFinder = new SnippetFinder(
            $kernel,
            $connection
        );

        $snippets = $snippetFinder->findSnippets('en-GB');

        if (!empty($expectedSnippets)) {
            $key = array_key_first($expectedSnippets);
            static::assertEquals($expectedSnippets[$key], $snippets[$key]);
        } else {
            // assert no exception was thrown
            static::assertTrue(true);
        }
    }

    /**
     * @dataProvider validateAppSnippetsExceptionDataProvider
     *
     * @param array<string, mixed> $existingSnippets
     * @param array<string, mixed> $appSnippets
     * @param list<string> $duplicatedSnippets
     */
    public function testValidateSnippets(array $existingSnippets, array $appSnippets, array $duplicatedSnippets): void
    {
        $exceptionWasThrown = false;
        $expectedExceptionMessage = 'The following keys on the first level are duplicated and can not be overwritten: ' . implode(', ', $duplicatedSnippets);

        $snippetFinder = new SnippetFinder(
            $this->createMock(Kernel::class),
            $this->createMock(Connection::class)
        );

        $reflectionClass = new \ReflectionClass(SnippetFinder::class);
        $reflectionMethod = $reflectionClass->getMethod('validateAppSnippets');
        $reflectionMethod->setAccessible(true);

        try {
            $reflectionMethod->invoke($snippetFinder, $existingSnippets, $appSnippets);
            /** @phpstan-ignore-next-line does not check that a SnippetException will be thrown */
        } catch (SnippetException $exception) {
            static::assertEquals($expectedExceptionMessage, $exception->getMessage());

            $exceptionWasThrown = true;
        } finally {
            /** @phpstan-ignore-next-line does not check that $exceptionWasThrown might change */
            static::assertTrue($exceptionWasThrown, 'Expected exception with the following message to be thrown: ' . $expectedExceptionMessage);
        }
    }

    /**
     * @dataProvider sanitizeAppSnippetDataProvider
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function testSanitizeAppSnippets(array $before, array $after): void
    {
        $snippetFinder = new SnippetFinder(
            $this->createMock(Kernel::class),
            $this->createMock(Connection::class)
        );

        $reflectionClass = new \ReflectionClass(SnippetFinder::class);
        $reflectionMethod = $reflectionClass->getMethod('sanitizeAppSnippets');
        $reflectionMethod->setAccessible(true);
        $result = $reflectionMethod->invoke($snippetFinder, $before);

        static::assertEquals($after, $result);
    }

    /**
     * @return array<string, array{kernel: Kernel, expectedSnippets: array<string, mixed>, connection: Connection}>
     */
    public function findSnippetsFromAppDataProvider(): iterable
    {
        yield 'Test no snippets are provided' => [
            'kernel' => $this->getKernelWithNoPlugins(),
            'expectedSnippets' => [],
            'connection' => $this->getConnectionMock('en-GB', []),
        ];

        yield 'Test snippets are provided' => [
            'kernel' => $this->getKernelWithNoPlugins(),
            'expectedSnippets' => $this->getSnippetFixtures(),
            'connection' => $this->getConnectionMock('en-GB', $this->getSnippetFixtures()),
        ];
    }

    /**
     * @return array<string, array{existingSnippets: array<string, mixed>, appSnippets: array<string, mixed>, duplicatedSnippets: list<string>}>
     */
    public function validateAppSnippetsExceptionDataProvider(): iterable
    {
        yield 'Throw exception if existing snippets will be overwritten' => [
            'existingSnippets' => [
                'core' => [],
            ],
            'appSnippets' => [
                'my-app-snippets' => [],
                'core' => [
                    'foo' => 'this will extend or overwrite the core',
                ],
            ],
            'duplicatedSnippets' => [
                'core',
            ],
        ];
    }

    /**
     * @return array<string, array{before: array<string, mixed>, after: array<string, mixed>}>
     */
    public function sanitizeAppSnippetDataProvider(): iterable
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
    public function getConnectionMock(string $expectedLocale, array $snippets): Connection
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

    private function getKernelWithNoPlugins(): Kernel
    {
        $pluginLoader = new StaticKernelPluginLoader(new ClassLoader());

        return new Kernel(
            'dev',
            false,
            $pluginLoader,
            'foobar'
        );
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
