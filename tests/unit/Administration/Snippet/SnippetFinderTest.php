<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\SnippetException;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Core\Kernel;

/**
 * @internal
 */
#[CoversClass(SnippetFinder::class)]
class SnippetFinderTest extends TestCase
{
    public function testFindSnippetsFromAppNoSnippetsAdded(): void
    {
        $snippetFinder = new SnippetFinder(
            $this->createMock(Kernel::class),
            $this->getConnectionMock('en-GB', [])
        );

        $snippets = $snippetFinder->findSnippets('en-GB');
        static::assertArrayNotHasKey('my-custom-snippet-key', $snippets);
    }

    public function testFindSnippetsFromApp(): void
    {
        $snippetFinder = new SnippetFinder(
            $this->createMock(Kernel::class),
            $this->getConnectionMock('en-GB', $this->getSnippetFixtures())
        );

        $snippets = $snippetFinder->findSnippets('en-GB');

        $expectedSnippets = $this->getSnippetFixtures();
        $key = array_key_first($expectedSnippets);
        static::assertEquals($expectedSnippets[$key], $snippets[$key]);
    }

    /**
     * @param array<string, mixed> $existingSnippets
     * @param list<string> $duplicatedSnippets
     */
    #[DataProvider('validateAppSnippetsExceptionDataProvider')]
    public function testValidateSnippets(array $existingSnippets, array $duplicatedSnippets): void
    {
        $exceptionWasThrown = false;
        $expectedExceptionMessage = 'The following keys on the first level are duplicated and can not be overwritten: ' . implode(', ', $duplicatedSnippets);

        $snippetFinder = new SnippetFinder(
            $this->createMock(Kernel::class),
            $this->getConnectionMock('en-GB', $existingSnippets)
        );

        try {
            $snippetFinder->findSnippets('en-GB');
        } catch (SnippetException $exception) {
            static::assertEquals($expectedExceptionMessage, $exception->getMessage());

            $exceptionWasThrown = true;
        } finally {
            static::assertTrue($exceptionWasThrown, 'Expected exception with the following message to be thrown: ' . $expectedExceptionMessage);
        }
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    #[DataProvider('sanitizeAppSnippetDataProvider')]
    public function testSanitizeAppSnippets(array $before, array $after): void
    {
        $snippetFinder = new SnippetFinder(
            $this->createMock(Kernel::class),
            $this->getConnectionMock('en-GB', $before)
        );

        $result = $snippetFinder->findSnippets('en-GB');
        $result = array_intersect_key($result, $before); // filter out all others snippets

        static::assertEquals($after, $result);
    }

    /**
     * @return array<string, array{existingSnippets: array<string, mixed>, duplicatedSnippets: list<string>}>
     */
    public static function validateAppSnippetsExceptionDataProvider(): iterable
    {
        yield 'Throw exception if existing snippets will be overwritten' => [
            'existingSnippets' => [
                'sw-wizard' => [],
            ],
            'duplicatedSnippets' => [
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
