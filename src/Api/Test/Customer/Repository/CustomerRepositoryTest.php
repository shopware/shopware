<?php declare(strict_types=1);

namespace Shopware\Api\Test\Customer\Repository;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Customer\Definition\CustomerDefinition;
use Shopware\Api\Customer\Repository\CustomerRepository;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Term\EntityScoreQueryBuilder;
use Shopware\Api\Entity\Search\Term\SearchTermInterpreter;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomerRepositoryTest extends KernelTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->container = self::$kernel->getContainer();
        $this->repository = $this->container->get(CustomerRepository::class);
        $this->connection = $this->container->get(Connection::class);
        $this->connection->executeUpdate('DELETE FROM `order`');
        $this->connection->executeUpdate('DELETE FROM customer');
        $this->connection->beginTransaction();
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testSearchRanking()
    {
        $recordA = Uuid::uuid4()->toString();
        $recordB = Uuid::uuid4()->toString();
        $recordC = Uuid::uuid4()->toString();
        $recordD = Uuid::uuid4()->toString();

        $address = [
            'firstName' => 'not',
            'lastName' => 'not',
            'city' => 'not',
            'street' => 'not',
            'zipcode' => 'not',
            'salutation' => 'not',
            'country' => ['name' => 'not'],
        ];

        $records = [
            [
                'id' => $recordA,
                'shopId' => Defaults::SHOP,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => 'e84976ac-e9ab-4928-a3dc-c387b66dbaa6',
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'not',
                'password' => 'not',
                'lastName' => 'not',
                'firstName' => 'match',
                'salutation' => 'not',
                'number' => 'not',
            ],
            [
                'id' => $recordB,
                'shopId' => Defaults::SHOP,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => 'e84976ac-e9ab-4928-a3dc-c387b66dbaa6',
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'not',
                'password' => 'not',
                'lastName' => 'match',
                'firstName' => 'not',
                'salutation' => 'not',
                'number' => 'not',
            ],
            [
                'id' => $recordC,
                'shopId' => Defaults::SHOP,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => 'e84976ac-e9ab-4928-a3dc-c387b66dbaa6',
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'not',
                'password' => 'not',
                'lastName' => 'not',
                'firstName' => 'not',
                'salutation' => 'not',
                'number' => 'match',
            ],
            [
                'id' => $recordD,
                'shopId' => Defaults::SHOP,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => 'e84976ac-e9ab-4928-a3dc-c387b66dbaa6',
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'match',
                'password' => 'not',
                'lastName' => 'not',
                'firstName' => 'not',
                'salutation' => 'not',
                'number' => 'not',
            ],
        ];

        $this->repository->create($records, ShopContext::createDefaultContext());

        $criteria = new Criteria();

        $builder = $this->container->get(EntityScoreQueryBuilder::class);
        $pattern = $this->container->get(SearchTermInterpreter::class)->interpret('match', ShopContext::createDefaultContext());
        $queries = $builder->buildScoreQueries($pattern, CustomerDefinition::class, CustomerDefinition::getEntityName());
        $criteria->addQueries($queries);

        $result = $this->repository->searchIds($criteria, ShopContext::createDefaultContext());

        $this->assertCount(4, $result->getIds());

        $this->assertEquals(
            $result->getDataFieldOfId($recordA, 'score'),
            $result->getDataFieldOfId($recordB, 'score')
        );

        $this->assertEquals(
            $result->getDataFieldOfId($recordC, 'score'),
            $result->getDataFieldOfId($recordD, 'score')
        );

        $this->assertTrue(
            $result->getDataFieldOfId($recordC, 'score')
            >
            $result->getDataFieldOfId($recordA, 'score')
        );
    }
}
