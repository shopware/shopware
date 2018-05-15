<?php declare(strict_types=1);

namespace Shopware\Api\Test\Customer\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Checkout\Customer\Definition\CustomerDefinition;
use Shopware\Checkout\Customer\Repository\CustomerRepository;
use Shopware\Framework\ORM\RepositoryInterface;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Framework\ORM\Search\Term\SearchTermInterpreter;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
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
        $recordA = Uuid::uuid4()->getHex();
        $recordB = Uuid::uuid4()->getHex();
        $recordC = Uuid::uuid4()->getHex();
        $recordD = Uuid::uuid4()->getHex();

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
                'applicationId' => Defaults::APPLICATION,
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
                'applicationId' => Defaults::APPLICATION,
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
                'applicationId' => Defaults::APPLICATION,
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
                'applicationId' => Defaults::APPLICATION,
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

        $this->repository->create($records, ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new Criteria();

        $builder = $this->container->get(EntityScoreQueryBuilder::class);
        $pattern = $this->container->get(SearchTermInterpreter::class)->interpret('match', ApplicationContext::createDefaultContext(Defaults::TENANT_ID));
        $queries = $builder->buildScoreQueries($pattern, CustomerDefinition::class, CustomerDefinition::getEntityName());
        $criteria->addQueries($queries);

        $result = $this->repository->searchIds($criteria, ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        $this->assertCount(4, $result->getIds());

        $this->assertEquals(
            $result->getDataFieldOfId($recordA, '_score'),
            $result->getDataFieldOfId($recordB, '_score')
        );

        $this->assertEquals(
            $result->getDataFieldOfId($recordC, '_score'),
            $result->getDataFieldOfId($recordD, '_score')
        );

        $this->assertTrue(
            $result->getDataFieldOfId($recordC, '_score')
            >
            $result->getDataFieldOfId($recordA, '_score')
        );
    }
}
