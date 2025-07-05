<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
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
#[Package('framework')]
#[CoversClass(SearchConfigLoader::class)]
class SearchConfigLoaderTest extends TestCase
{
    private Connection&MockObject $connection;

    private SearchConfigLoader $searchConfigLoader;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->searchConfigLoader = new SearchConfigLoader($this->connection);
    }

    /**
     * @param array<non-falsy-string, array<array{and_logic: string, field: string, tokenize: int, ranking: float}>> $configKeyedByLanguageId
     * @param array<array{and_logic: string, field: string, tokenize: int, ranking: float}> $expectedResult
     */
    #[DataProvider('loadDataProvider')]
    public function testLoad(array $configKeyedByLanguageId, array $expectedResult): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($configKeyedByLanguageId[array_key_first($configKeyedByLanguageId)]);

        $loader = new SearchConfigLoader($connection);

        $languageIdChain = array_values(array_filter(array_keys($configKeyedByLanguageId)));
        static::assertNotEmpty($languageIdChain);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            $languageIdChain,
        );

        $result = $loader->load($context);

        static::assertSame($expectedResult, $result);
    }

    public function testLoadWithNoResult(): void
    {
        static::expectExceptionObject(ElasticsearchProductException::configNotFound());
        static::expectExceptionMessage('Configuration for product elasticsearch definition not found');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
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

    public function testLoadFilterConfigReturnsCachedConfig(): void
    {
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $cachedConfig = [
            'excludedTerms' => ['term1' => true, 'term2' => true],
            'minSearchLength' => 3,
        ];

        // Set the cached config
        $reflection = new \ReflectionClass($this->searchConfigLoader);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setValue($this->searchConfigLoader, [$languageId => $cachedConfig]);

        $result = $this->searchConfigLoader->loadFilterConfig($languageId);

        static::assertSame($cachedConfig, $result);
    }

    public function testLoadFilterConfigReturnsValidConfig(): void
    {
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $dbResult = [
            'excluded_terms' => json_encode(['term1', 'term2'], \JSON_THROW_ON_ERROR),
            'min_search_length' => 5,
        ];

        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn($dbResult);

        $result = $this->searchConfigLoader->loadFilterConfig($languageId);

        static::assertSame([
            'excludedTerms' => ['term1' => 0, 'term2' => 1],
            'minSearchLength' => 5,
        ], $result);
    }

    public function testLoadFilterConfigReturnsNullWhenNoData(): void
    {
        $languageId = Defaults::LANGUAGE_SYSTEM;

        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $result = $this->searchConfigLoader->loadFilterConfig($languageId);

        static::assertNull($result);
    }
}
