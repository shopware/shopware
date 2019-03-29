<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Search\CompositeEntitySearcher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class CompositeEntitySearcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CompositeEntitySearcher
     */
    private $search;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $userId;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->search = $this->getContainer()->get(CompositeEntitySearcher::class);

        $this->userId = Uuid::randomHex();

        $origin = new AdminApiSource($this->userId);
        $this->context = Context::createDefaultContext($origin);

        $repo = $this->getContainer()->get('user.repository');
        $repo->upsert([
            [
                'id' => $this->userId,
                'localeId' => '20080911ffff4fffafffffff19830531',
                'name' => 'test-user',
                'username' => 'test-user',
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'shopware',
            ],
        ], $this->context);
    }

    public function testProductRanking(): void
    {
        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();

        $filterId = Uuid::randomHex();

        $this->productRepository->upsert([
            ['id' => $productId1, 'stock' => 1, 'name' => "${filterId}_test ${filterId}_product 1", 'price' => ['gross' => 10, 'net' => 9, 'linked' => false], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test']],
            ['id' => $productId2, 'stock' => 1, 'name' => "${filterId}_test ${filterId}_product 2", 'price' => ['gross' => 10, 'net' => 9, 'linked' => false], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['name' => 'test']],
            ['id' => Uuid::randomHex(), 'stock' => 1, 'name' => 'notmatch', 'price' => ['gross' => 10, 'net' => 9, 'linked' => false], 'tax' => ['name' => 'notmatch', 'taxRate' => 5], 'manufacturer' => ['name' => 'notmatch']],
            ['id' => Uuid::randomHex(), 'stock' => 1, 'name' => 'notmatch', 'price' => ['gross' => 10, 'net' => 9, 'linked' => false], 'tax' => ['name' => 'notmatch', 'taxRate' => 5], 'manufacturer' => ['name' => 'notmatch']],
        ], $this->context);

        $result = $this->search->search("${filterId}_test ${filterId}_product", 20, $this->context, $this->userId);

        /** @var ProductEntity $first */
        $first = $result['data'][0]['entity'];
        static::assertInstanceOf(ProductEntity::class, $first);

        /** @var ProductEntity $second */
        $second = $result['data'][1]['entity'];
        static::assertInstanceOf(ProductEntity::class, $second);

        $firstScore = $first->getExtension('search')->get('_score');
        $secondScore = $second->getExtension('search')->get('_score');

        static::assertSame($secondScore, $firstScore);

        $this->productRepository->update([
            ['id' => $productId2, 'price' => ['gross' => 15, 'net' => 1, 'linked' => false]],
            ['id' => $productId2, 'price' => ['gross' => 20, 'net' => 1, 'linked' => false]],
            ['id' => $productId2, 'price' => ['gross' => 25, 'net' => 1, 'linked' => false]],
            ['id' => $productId2, 'price' => ['gross' => 30, 'net' => 1, 'linked' => false]],
        ], $this->context);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId2, Defaults::LIVE_VERSION);
        static::assertNotEmpty($changes);

        $result = $this->search->search("${filterId}_test ${filterId}_product", 20, $this->context, $this->userId);

        static::assertCount(2, $result['data']);

        /** @var ProductEntity $first */
        $first = $result['data'][0]['entity'];
        static::assertInstanceOf(ProductEntity::class, $first);

        /** @var ProductEntity $second */
        $second = $result['data'][1]['entity'];
        static::assertInstanceOf(ProductEntity::class, $second);

        // `product-2` should now be boosted
        static::assertSame($first->getId(), $productId2);
        static::assertSame($second->getId(), $productId1);

        $firstScore = $result['data'][0]['_score'];
        $secondScore = $result['data'][1]['_score'];

        static::assertTrue($firstScore > $secondScore, print_r($firstScore . ' < ' . $secondScore, true));
    }

    private function getVersionData(string $entity, string $id, string $versionId): array
    {
        return $this->connection->fetchAll(
            "SELECT d.* 
             FROM version_commit_data d
             INNER JOIN version_commit c
               ON c.id = d.version_commit_id
               AND c.version_id = :version
             WHERE entity_name = :entity 
             AND JSON_EXTRACT(entity_id, '$.id') = :id
             ORDER BY auto_increment",
            [
                'entity' => $entity,
                'id' => $id,
                'version' => Uuid::fromHexToBytes($versionId),
            ]
        );
    }
}
