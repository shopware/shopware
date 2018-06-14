<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerRepository;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\ORM\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CustomerRepositoryTest extends KernelTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$container->get(CustomerRepository::class);
        $this->connection = self::$container->get(Connection::class);
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
                'touchpointId' => Defaults::TOUCHPOINT,
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
                'touchpointId' => Defaults::TOUCHPOINT,
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
                'touchpointId' => Defaults::TOUCHPOINT,
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
                'touchpointId' => Defaults::TOUCHPOINT,
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

        $this->repository->create($records, Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new Criteria();

        $builder = self::$container->get(EntityScoreQueryBuilder::class);
        $pattern = self::$container->get(SearchTermInterpreter::class)->interpret('match', Context::createDefaultContext(Defaults::TENANT_ID));
        $queries = $builder->buildScoreQueries($pattern, CustomerDefinition::class, CustomerDefinition::getEntityName());
        $criteria->addQueries($queries);

        $result = $this->repository->searchIds($criteria, Context::createDefaultContext(Defaults::TENANT_ID));

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
