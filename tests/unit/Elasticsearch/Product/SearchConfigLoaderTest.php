<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Product\ElasticsearchProductException;
use Shopware\Elasticsearch\Product\SearchConfigLoader;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(SearchConfigLoader::class)]
class SearchConfigLoaderTest extends TestCase
{
    /**
     * @param array<string, array<array{and_logic: string, field: string, tokenize: int, ranking: float}>> $configKeyedByLanguageId
     * @param array<array{and_logic: string, field: string, tokenize: int, ranking: float}> $expectedResult
     */
    #[DataProvider('loadDataProvider')]
    public function testLoad(array $configKeyedByLanguageId, array $expectedResult): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn($configKeyedByLanguageId[array_key_first($configKeyedByLanguageId)]);

        $loader = new SearchConfigLoader($connection);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            array_filter(array_keys($configKeyedByLanguageId)),
        );

        $result = $loader->load($context);

        static::assertEquals($expectedResult, $result);
    }

    public function testLoadWithNoResult(): void
    {
        static::expectExceptionObject(ElasticsearchProductException::configNotFound());
        static::expectExceptionMessage('Configuration for product elasticsearch definition not found');

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $loader = new SearchConfigLoader($connection);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM],
        );

        $loader->load($context);
    }

    /**
     * @return iterable<string, array{configKeyedByLanguageId: array<string, array<array{and_logic: string, field: string, tokenize: int, ranking: int}>>, expectedResult: array<array{and_logic: string, field: string, tokenize: int, ranking: int}>}>
     */
    public static function loadDataProvider(): iterable
    {
        yield 'one language config' => [
            'configKeyedByLanguageId' => [
                Defaults::LANGUAGE_SYSTEM => [[
                    'and_logic' => 'and',
                    'field' => 'name',
                    'tokenize' => 1,
                    'ranking' => 2,
                ]],
            ],
            'expectedResult' => [
                [
                    'and_logic' => 'and',
                    'field' => 'name',
                    'tokenize' => 1,
                    'ranking' => 2,
                ],
            ],
        ];

        yield 'multi languages config' => [
            'configKeyedByLanguageId' => [
                Defaults::LANGUAGE_SYSTEM => [[
                    'and_logic' => 'and',
                    'field' => 'name',
                    'tokenize' => 1,
                    'ranking' => 100,
                ]],
                Uuid::randomHex() => [[
                    'and_logic' => 'and',
                    'field' => 'name',
                    'tokenize' => 0,
                    'ranking' => 50,
                ]],
            ],
            'expectedResult' => [
                [
                    'and_logic' => 'and',
                    'field' => 'name',
                    'tokenize' => 1,
                    'ranking' => 100,
                ],
            ],
        ];
    }
}
