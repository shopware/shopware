<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Search;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Administration\Search\AuditLogSearch;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Definition\ProductDefinition;
use Shopware\Product\Repository\ProductRepository;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\User\Repository\UserRepository;
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

        $repo = $this->container->get(UserRepository::class);
        $repo->upsert([
            [
                'uuid' => 'user-1',
                'localeUuid' => 'SWAG-LOCALE-UUID-1',
                'name' => 'test-user',
                'username' => 'test-user',
                'email' => 'test@example.com',
                'roleUuid' => 'test',
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

        $this->productRepository->upsert([
            ['uuid' => 'product-1', 'name' => 'test product 1'],
            ['uuid' => 'product-2', 'name' => 'test product 2'],
            ['uuid' => 'product-3', 'name' => 'notmatch'],
            ['uuid' => 'product-4', 'name' => 'notmatch'],
        ], $context);

        $result = $this->search->search('test product', 1, 20, $context, 'user-1');

        //no audit log exists? product 1 was insert first and should match first
        self::assertEquals(2, $result['total']);
        self::assertCount(2, $result['data']);

        /** @var ProductBasicStruct $first */
        $first = $result['data'][0];
        self::assertInstanceOf(ProductBasicStruct::class, $first);

        /** @var ProductBasicStruct $second */
        $second = $result['data'][1];
        self::assertInstanceOf(ProductBasicStruct::class, $second);

        self::assertSame($first->getUuid(), 'product-1');
        self::assertSame($second->getUuid(), 'product-2');

        $firstScore = $first->getExtension('search')->get('score');
        $secondScore = $second->getExtension('search')->get('score');

        self::assertSame($secondScore, $firstScore);

        $logs = [
            [
                'uuid' => Uuid::uuid4()->toString(),
                'user_uuid' => 'user-1',
                'entity' => ProductDefinition::class,
                'foreign_key' => 'product-2',
                'action' => 'insert',
                'payload' => json_encode(''),
                'created_at' => '2017-01-01',
            ],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'user_uuid' => 'user-1',
                'entity' => ProductDefinition::class,
                'foreign_key' => 'product-2',
                'action' => 'update',
                'payload' => json_encode(''),
                'created_at' => '2017-01-02',
            ],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'user_uuid' => 'user-1',
                'entity' => ProductDefinition::class,
                'foreign_key' => 'product-2',
                'action' => 'upsert',
                'payload' => json_encode(''),
                'created_at' => '2017-01-03',
            ],
        ];

        //now insert audit log operations for `product-2`
        foreach ($logs as $log) {
            $this->connection->insert('audit_log', $log);
        }

        $result = $this->search->search('test product', 1, 20, $context, 'user-1');

        self::assertEquals(2, $result['total']);
        self::assertCount(2, $result['data']);

        /** @var ProductBasicStruct $first */
        $first = $result['data'][0];
        self::assertInstanceOf(ProductBasicStruct::class, $first);

        /** @var ProductBasicStruct $second */
        $second = $result['data'][1];
        self::assertInstanceOf(ProductBasicStruct::class, $second);

        // `product-2` should now be boosted
        self::assertSame($first->getUuid(), 'product-2');
        self::assertSame($second->getUuid(), 'product-1');

        $firstScore = $first->getExtension('search')->get('score');
        $secondScore = $second->getExtension('search')->get('score');

        self::assertTrue($firstScore > $secondScore);
    }
}
