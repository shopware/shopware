<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ProductStream;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexingMessage;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1770740885AddRevisionToConsentState;
use Shopware\Core\Migration\V6_7\Migration1791622183ConvertProductStreamPercentageFiltersToRatio;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('inventory')]
class PercentageRatioProductStreamMigrationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private const PERCENTAGE = 'cheapestPrice.percentage';

    /**
     * @param list<array<string, mixed>> $conditions
     * @param list<string> $expectedProducts
     */
    #[DataProvider('productGroupProvider')]
    public function testGroupMatchesTheProductsItWasWrittenFor(array $conditions, array $expectedProducts, bool $brokenBeforeTheMigration = true): void
    {
        $context = Context::createDefaultContext();
        $products = [
            '25 % off' => $this->createProduct(75.0, 100.0, $context),
            '5 % off' => $this->createProduct(95.0, 100.0, $context),
            'list price equals price' => $this->createProduct(100.0, 100.0, $context),
            'without list price' => $this->createProduct(95.0, null, $context),
        ];
        $streamId = $this->createGroup($conditions, $context);
        // the products of a new group are assigned to it in the background
        $this->runWorker();

        $matchesBeforeMigration = $this->matchingProducts($streamId, $products, $context);

        $this->runMigrationAndQueuedIndexers();

        static::assertEqualsCanonicalizing($expectedProducts, $this->matchingProducts($streamId, $products, $context));
        static::assertEqualsCanonicalizing($expectedProducts, $this->groupMembers($streamId, $products, $context), 'the products are not assigned to the group they match');

        $compiledFilter = $this->compiledFilter($streamId);
        static::getContainer()->get(ProductStreamIndexer::class)->handle(new ProductStreamIndexingMessage([$streamId], null, $context));
        static::assertEquals($this->compiledFilter($streamId), $compiledFilter, 'the compiled filter of the group differs from its conditions');

        if ($brokenBeforeTheMigration) {
            static::assertNotEqualsCanonicalizing($expectedProducts, $matchesBeforeMigration, 'the group was not broken before the migration');
        } else {
            static::assertEqualsCanonicalizing($expectedProducts, $matchesBeforeMigration);
        }
    }

    /**
     * @return iterable<string, array{0: list<array<string, mixed>>, 1: list<string>, 2?: bool}>
     */
    public static function productGroupProvider(): iterable
    {
        yield 'at least 10 % off' => [
            [self::percentageRange(['gte' => 10])],
            ['25 % off'],
        ];
        yield 'any discount' => [
            [self::percentageRange(['gte' => 0.1])],
            ['25 % off', '5 % off'],
        ];
        yield 'more than 1 % off' => [
            [self::percentageRange(['gt' => 1])],
            ['25 % off', '5 % off'],
        ];
        // read as a discount it would hold for every product with a list price, so it was written for the ratio
        yield 'less than 100, written for the ratio before the update' => [
            [self::percentageRange(['lt' => 100])],
            ['25 % off', '5 % off'],
            false,
        ];
        // products without a list price have no percentage and never match a percentage condition
        yield 'at most 10 % off' => [
            [self::percentageRange(['lte' => 10])],
            ['5 % off', 'list price equals price'],
        ];
        yield 'between 5 and 30 % off' => [
            [self::percentageRange(['gte' => 5, 'lte' => 30])],
            ['25 % off', '5 % off'],
        ];
        yield 'exactly 25 % off' => [
            [['type' => 'equals', 'field' => self::PERCENTAGE, 'value' => '25']],
            ['25 % off'],
        ];
        yield 'exactly 10 or 25 % off' => [
            [['type' => 'equalsAny', 'field' => self::PERCENTAGE, 'value' => '10|25']],
            ['25 % off'],
        ];
        yield 'not exactly 25 % off' => [
            [['type' => 'not', 'queries' => [['type' => 'equals', 'field' => self::PERCENTAGE, 'value' => '25', 'position' => 0]]]],
            ['5 % off', 'list price equals price', 'without list price'],
        ];
        yield 'in stock and more than 1 % off' => [
            [['type' => 'range', 'field' => 'stock', 'parameters' => ['gte' => 1]], self::percentageRange(['gt' => 1])],
            ['25 % off', '5 % off'],
        ];
        yield 'created after the update with the default of the administration' => [
            [self::percentageRange(['lt' => 100], writtenBeforeTheRatioRelease: false)],
            ['25 % off', '5 % off'],
            false,
        ];
        yield 'already converted by hand after the update' => [
            [self::percentageRange(['lte' => 90], writtenBeforeTheRatioRelease: false)],
            ['25 % off'],
            false,
        ];
        yield 'condition from before next to one added after the update' => [
            [self::percentageRange(['gte' => 10]), self::percentageRange(['lt' => 100], writtenBeforeTheRatioRelease: false)],
            ['25 % off'],
        ];
    }

    /**
     * @param array<string, float|int> $bounds
     *
     * @return array<string, mixed>
     */
    private static function percentageRange(array $bounds, bool $writtenBeforeTheRatioRelease = true): array
    {
        return [
            'type' => 'range',
            'field' => self::PERCENTAGE,
            'parameters' => $bounds,
            'writtenBeforeTheRatioRelease' => $writtenBeforeTheRatioRelease,
        ];
    }

    /**
     * Creates the group the way the administration does, an OR container holding an AND container with the conditions
     *
     * @param list<array<string, mixed>> $conditions
     */
    private function createGroup(array $conditions, Context $context): string
    {
        $streamId = Uuid::randomHex();
        $writtenSinceTheRatioRelease = [];
        $queries = [];

        foreach ($conditions as $position => $condition) {
            $condition['id'] = Uuid::randomHex();
            $condition['position'] = $position;

            if (($condition['writtenBeforeTheRatioRelease'] ?? true) === false) {
                $writtenSinceTheRatioRelease[] = $condition['id'];
            }
            unset($condition['writtenBeforeTheRatioRelease']);

            $queries[] = $condition;
        }

        static::getContainer()->get('product_stream.repository')->create([[
            'id' => $streamId,
            'name' => 'Sale',
            'filters' => [[
                'type' => 'multi',
                'operator' => 'OR',
                'position' => 0,
                'queries' => [[
                    'type' => 'multi',
                    'operator' => 'AND',
                    'position' => 0,
                    'queries' => $queries,
                ]],
            ]],
        ]], $context);

        $connection = static::getContainer()->get(Connection::class);
        $ratioSince = $connection->fetchOne(
            'SELECT `update` FROM `migration` WHERE `class` = :class',
            ['class' => Migration1770740885AddRevisionToConsentState::class]
        );
        static::assertIsString($ratioSince);

        $connection->executeStatement(
            'UPDATE `product_stream_filter` SET `created_at` = :createdAt, `updated_at` = NULL
             WHERE `product_stream_id` = :streamId AND `id` NOT IN (:writtenSince)',
            [
                'createdAt' => (new \DateTimeImmutable($ratioSince))->modify('-1 day')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'streamId' => Uuid::fromHexToBytes($streamId),
                'writtenSince' => Uuid::fromHexToBytesList([...$writtenSinceTheRatioRelease, Uuid::randomHex()]),
            ],
            ['writtenSince' => ArrayParameterType::BINARY]
        );

        return $streamId;
    }

    /**
     * Runs the migration and the indexers it queued in the worst order the message queue allows after `system:update:finish`:
     * the products are assigned to the groups before any group is compiled again
     */
    private function runMigrationAndQueuedIndexers(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->delete('system_config', ['configuration_key' => IndexerQueuer::INDEXER_KEY]);

        (new Migration1791622183ConvertProductStreamPercentageFiltersToRatio())->update($connection);

        $queued = (new IndexerQueuer($connection))->getIndexers();
        $registry = static::getContainer()->get(EntityIndexerRegistry::class);

        foreach (['product.indexer', 'product_stream.indexer'] as $name) {
            if (!\array_key_exists($name, $queued)) {
                continue;
            }

            $indexer = $registry->getIndexer($name);
            static::assertNotNull($indexer);
            $skip = $queued[$name] === [] ? [] : array_values(array_diff($indexer->getOptions(), $queued[$name]));

            $registry->index(false, $skip, [$name], true);
        }

        $this->runWorker();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function compiledFilter(string $streamId): array
    {
        $apiFilter = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT `api_filter` FROM `product_stream` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($streamId)]
        );
        static::assertIsString($apiFilter);

        return json_decode($apiFilter, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, string> $products
     *
     * @return list<string>
     */
    private function groupMembers(string $streamId, array $products, Context $context): array
    {
        /** @var EntityRepository<ProductCollection> $repository */
        $repository = static::getContainer()->get('product.repository');
        $members = $repository
            ->search(new Criteria(array_values($products)), $context)
            ->getEntities()
            ->filter(static fn (ProductEntity $product): bool => \in_array($streamId, $product->getStreamIds() ?? [], true))
            ->getIds();

        return array_keys(array_intersect($products, $members));
    }

    /**
     * @param array<string, string> $products
     *
     * @return list<string>
     */
    private function matchingProducts(string $streamId, array $products, Context $context): array
    {
        $criteria = new Criteria(array_values($products));
        static::getContainer()->get(ProductStreamBuilder::class)->enrichCriteria($criteria, $streamId, $context);

        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $matches = static::getContainer()->get('sales_channel.product.repository')
            ->searchIds($criteria, $salesChannelContext)
            ->getIds();

        return array_keys(array_intersect($products, $matches));
    }

    private function createProduct(float $gross, ?float $listGross, Context $context): string
    {
        $id = Uuid::randomHex();
        $price = ['currencyId' => Defaults::CURRENCY, 'gross' => $gross, 'net' => $gross, 'linked' => false];
        if ($listGross !== null) {
            $price['listPrice'] = ['gross' => $listGross, 'net' => $listGross, 'linked' => false];
        }

        static::getContainer()->get('product.repository')->create([[
            'id' => $id,
            'productNumber' => $id,
            'name' => 'Percentage stream ' . $id,
            'stock' => 10,
            'price' => [$price],
            'tax' => ['name' => 'test', 'taxRate' => 19],
            'visibilities' => [['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => 30]],
        ]], $context);

        return $id;
    }
}
