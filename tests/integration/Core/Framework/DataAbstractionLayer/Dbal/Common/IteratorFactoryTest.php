<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\LastIdQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\OffsetQuery;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class IteratorFactoryTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private IteratorFactory $factory;

    /**
     * @var list<string>
     */
    private array $ids = [
        '00000000000000000000000000000000',
        '31323334353637383930313233343536',
        'ffffffffffffffffffffffffffffffff',
    ];

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->factory = static::getContainer()->get(IteratorFactory::class);

        foreach (array_reverse($this->ids) as $id) {
            $this->connection->insert('media', [
                'id' => Uuid::fromHexToBytes($id),
                'created_at' => '2026-11-17 00:00:00.000',
            ]);
        }
    }

    public function testUuidPaginationCanResumeFromASerializedCursor(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $iterator = $this->factory->createIterator('media', limit: 2);
        $this->restrictToFixtureIds($iterator);

        static::assertInstanceOf(LastIdQuery::class, $iterator);
        static::assertStringNotContainsString(':lastId', $iterator->getQuery()->getSQL());
        static::assertSame(3, $iterator->fetchCount());
        static::assertSame(\array_slice($this->ids, 0, 2), array_values($iterator->fetch()));
        static::assertSame(['offset' => $this->ids[1]], $iterator->getOffset());
        $offset = json_decode(json_encode($iterator->getOffset(), \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(['offset' => $this->ids[1]], $offset);
        static::assertStringContainsString('.id > :lastId', $iterator->getQuery()->getSQL());
        static::assertSame(1, $iterator->fetchCount());
        static::assertSame([$this->ids[2]], array_values($iterator->fetch()));

        $this->connection->delete('media', ['id' => Uuid::fromHexToBytes($this->ids[0])]);
        $resumed = $this->factory->createIterator('media', $offset, limit: 2);
        $this->restrictToFixtureIds($resumed);

        static::assertSame(1, $resumed->fetchCount());
        static::assertSame([$this->ids[2]], array_values($resumed->fetch()));
        static::assertSame([], $resumed->fetch());
        static::assertSame(['offset' => $this->ids[2]], $resumed->getOffset());
        static::assertSame([], $resumed->fetch());
        static::assertSame(0, $resumed->getQuery()->getFirstResult());
    }

    /**
     * @deprecated tag:v6.8.0 - Numeric initial offsets are replaced by UUID cursors.
     */
    public function testInitialUuidIterationKeepsNumericOffsetsBefore68(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $iterator = $this->factory->createIterator('media', limit: 1);
        $this->restrictToFixtureIds($iterator);
        $iterator->getQuery()->addOrderBy('media.id');

        static::assertInstanceOf(OffsetQuery::class, $iterator);
        static::assertSame([$this->ids[0]], array_values($iterator->fetch()));
        static::assertSame(['offset' => 1], $iterator->getOffset());
    }

    public function testExistingUuidCursorsCanResumeInEitherFeatureMode(): void
    {
        $iterator = $this->factory->createIterator('media', ['offset' => $this->ids[1]], limit: 1);
        $this->restrictToFixtureIds($iterator);

        static::assertInstanceOf(LastIdQuery::class, $iterator);
        static::assertSame([$this->ids[2]], array_values($iterator->fetch()));
        static::assertSame(['offset' => $this->ids[2]], $iterator->getOffset());
    }

    public function testExistingNumericOffsetsKeepOffsetPagination(): void
    {
        $iterator = $this->factory->createIterator('media', ['offset' => 1], limit: 1);
        $this->restrictToFixtureIds($iterator);
        $iterator->getQuery()->addOrderBy('media.id');

        static::assertInstanceOf(OffsetQuery::class, $iterator);
        static::assertSame([$this->ids[1]], array_values($iterator->fetch()));
        static::assertSame(['offset' => 2], $iterator->getOffset());
    }

    public function testCompositePrimaryKeysKeepOffsetPagination(): void
    {
        $iterator = $this->factory->createIterator(ProductManufacturerDefinition::ENTITY_NAME, versionId: Defaults::LIVE_VERSION);

        static::assertInstanceOf(OffsetQuery::class, $iterator);
    }

    public function testAutoIncrementPaginationRemainsNumeric(): void
    {
        $this->connection->insert('category', [
            'id' => Uuid::fromHexToBytes($this->ids[0]),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'type' => 'page',
            'created_at' => '2026-11-17 00:00:00.000',
        ]);
        $iterator = $this->factory->createIterator('category', limit: 1);
        $iterator->getQuery()->andWhere('category.id = :id')->setParameter('id', Uuid::fromHexToBytes($this->ids[0]));

        static::assertInstanceOf(LastIdQuery::class, $iterator);
        static::assertSame([$this->ids[0]], array_values($iterator->fetch()));
        $offset = $iterator->getOffset();
        static::assertIsInt($offset['offset']);
        static::assertSame([], $iterator->fetch());
        static::assertSame($offset, $iterator->getOffset());
    }

    private function restrictToFixtureIds(IterableQuery $iterator): void
    {
        $iterator->getQuery()
            ->andWhere('media.id IN (:ids)')
            ->setParameter('ids', Uuid::fromHexToBytesList($this->ids), ArrayParameterType::BINARY);
    }
}
