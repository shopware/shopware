<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Search;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Administration\Search\AuditLogSearch;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\ProductBasicStruct;
use Shopware\Api\User\Repository\UserRepository;
use Shopware\Context\Struct\TranslationContext;
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
     * @var AuditLogSearch
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

        $this->connection = $this->container->get('dbal_connection');
        $this->connection->beginTransaction();

        $this->productRepository = $this->container->get(ProductRepository::class);
        $this->search = $this->container->get('shopware.administration.search.audit_log_search');
        $this->context = $context = TranslationContext::createDefaultContext();

        $this->connection->executeUpdate('
            DELETE FROM `audit_log`;
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
        $p2 = Uuid::uuid4()->toString();

        $this->productRepository->upsert([
            ['id' => $p1, 'name' => 'test product 1', 'price' => 10, 'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9', 'manufacturer' => ['name' => 'test']],
            ['id' => $p2, 'name' => 'test product 2', 'price' => 10, 'taxId' => '49260353-68e3-4d9f-a695-e017d7a231b9', 'manufacturer' => ['name' => 'test']],
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

        $logs = [
            [
                'id' => Uuid::uuid4()->getBytes(),
                'user_id' => Uuid::fromString($this->userId)->getBytes(),
                'entity' => ProductDefinition::class,
                'foreign_key' => Uuid::fromString($p2)->getBytes(),
                'action' => 'insert',
                'payload' => json_encode(''),
                'created_at' => '2017-01-01',
            ],
            [
                'id' => Uuid::uuid4()->getBytes(),
                'user_id' => Uuid::fromString($this->userId)->getBytes(),
                'entity' => ProductDefinition::class,
                'foreign_key' => Uuid::fromString($p2)->getBytes(),
                'action' => 'update',
                'payload' => json_encode(''),
                'created_at' => '2017-01-02',
            ],
            [
                'id' => Uuid::uuid4()->getBytes(),
                'user_id' => Uuid::fromString($this->userId)->getBytes(),
                'entity' => ProductDefinition::class,
                'foreign_key' => Uuid::fromString($p2)->getBytes(),
                'action' => 'upsert',
                'payload' => json_encode(''),
                'created_at' => '2017-01-03',
            ],
        ];

        //now insert audit log operations for `product-2`
        foreach ($logs as $log) {
            $this->connection->insert('audit_log', $log);
        }

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
        self::assertSame($first->getId(), $p2);
        self::assertSame($second->getId(), $p1);

        $firstScore = $first->getExtension('search')->get('score');
        $secondScore = $second->getExtension('search')->get('score');

        self::assertTrue($firstScore > $secondScore);
    }
}
