<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Search;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Administration\Search\AdministrationSearch;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Api\User\Repository\UserRepository;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Defaults;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuditLogSearchTest extends KernelTestCase
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var AdministrationSearch
     */
    private $search;

    /**
     * @var TranslationContext
     */
    private $context;

    /**
     * @var string
     */
    private $userId;

    protected function setUp()
    {
        $kernel = self::bootKernel();
        $this->container = $kernel->getContainer();

        $this->connection = $this->container->get(Connection::class);
        $this->connection->beginTransaction();

        $this->productRepository = $this->container->get(ProductRepository::class);
        $this->search = $this->container->get(AdministrationSearch::class);
        $this->context = $context = TranslationContext::createDefaultContext();

        $this->connection->executeUpdate('
            DELETE FROM `version`;
            DELETE FROM `version_commit`;
            DELETE FROM `version_commit_data`;
            DELETE FROM `user`;
            DELETE FROM `order`;
            DELETE FROM `customer`;
            DELETE FROM `product`;
        ');

        $this->userId = Uuid::uuid4()->toString();

        $repo = $this->container->get(UserRepository::class);
        $repo->upsert([
            [
                'id' => $this->userId,
                'localeId' => '7b52d9dd-2b06-40ec-90be-9f57edf29be7',
                'name' => 'test-user',
                'username' => 'test-user',
                'email' => 'test@example.com',
                'password' => 'shopware',
            ],
        ], $context);

        parent::setUp();
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testProductRanking()
    {
        $context = TranslationContext::createDefaultContext();

        $p1 = Uuid::uuid4()->toString();
        $productId2 = Uuid::uuid4()->toString();

        $this->productRepository->upsert([
            ['id' => $p1, 'name' => 'test product 1', 'price' => 10, 'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9', 'manufacturer' => ['name' => 'test']],
            ['id' => $productId2, 'name' => 'test product 2', 'price' => 10, 'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9', 'manufacturer' => ['name' => 'test']],
            ['id' => Uuid::uuid4()->toString(), 'name' => 'notmatch', 'price' => 10, 'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9', 'manufacturer' => ['name' => 'test']],
            ['id' => Uuid::uuid4()->toString(), 'name' => 'notmatch', 'price' => 10, 'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9', 'manufacturer' => ['name' => 'test']],
        ], $context);

        $result = $this->search->search('test product', 1, 20, $context, $this->userId);

        //no audit log exists? product 1 was insert first and should match first
        self::assertEquals(2, $result['total']);
        self::assertCount(2, $result['data']);

        /** @var ProductBasicStruct $first */
        $first = $result['data'][0];
        self::assertInstanceOf(ProductBasicStruct::class, $first);

        /** @var ProductBasicStruct $second */
        $second = $result['data'][1];
        self::assertInstanceOf(ProductBasicStruct::class, $second);

        $firstScore = $first->getExtension('search')->get('score');
        $secondScore = $second->getExtension('search')->get('score');

        self::assertSame($secondScore, $firstScore);

        $this->productRepository->update([
            ['id' => $productId2, 'price' => 15],
            ['id' => $productId2, 'price' => 20],
            ['id' => $productId2, 'price' => 25],
            ['id' => $productId2, 'price' => 30],
        ], TranslationContext::createDefaultContext());

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId2, Defaults::LIVE_VERSION);
        $this->assertNotEmpty($changes);

        $this->connection->executeUpdate('UPDATE version_commit_data SET user_id = NULL');
        $this->connection->executeUpdate(
            "UPDATE version_commit_data SET user_id = :user
             WHERE entity_name = :entity 
             AND JSON_EXTRACT(entity_id, '$.id') = :id",
            [
                'id' => $productId2,
                'entity' => ProductDefinition::getEntityName(),
                'user' => Uuid::fromString($this->userId)->getBytes(),
            ]
        );

        $result = $this->search->search('test product', 1, 20, $context, $this->userId);

        self::assertEquals(2, $result['total']);
        self::assertCount(2, $result['data']);

        /** @var ProductBasicStruct $first */
        $first = $result['data'][0];
        self::assertInstanceOf(ProductBasicStruct::class, $first);

        /** @var ProductBasicStruct $second */
        $second = $result['data'][1];
        self::assertInstanceOf(ProductBasicStruct::class, $second);

        // `product-2` should now be boosted
        self::assertSame($first->getId(), $productId2);
        self::assertSame($second->getId(), $p1);

        $firstScore = $first->getExtension('search')->get('score');
        $secondScore = $second->getExtension('search')->get('score');

        self::assertTrue($firstScore > $secondScore);
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
             ORDER BY ai",
            [
                'entity' => $entity,
                'id' => $id,
                'version' => Uuid::fromString($versionId)->getBytes(),
            ]
        );
    }
}
