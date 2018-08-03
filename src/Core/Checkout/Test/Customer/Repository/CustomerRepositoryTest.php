<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\ORM\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Util\Random;
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
        $this->repository = self::$container->get('customer.repository');
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

        $matchTerm = Random::getAlphanumericString(20);

        $records = [
            [
                'id' => $recordA,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => 'e84976ac-e9ab-4928-a3dc-c387b66dbaa6',
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::uuid4()->getHex() . '@example.com',
                'password' => 'not',
                'lastName' => 'not',
                'firstName' => $matchTerm,
                'salutation' => 'not',
                'number' => 'not',
            ],
            [
                'id' => $recordB,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => 'e84976ac-e9ab-4928-a3dc-c387b66dbaa6',
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::uuid4()->getHex() . '@example.com',
                'password' => 'not',
                'lastName' => $matchTerm,
                'firstName' => 'not',
                'salutation' => 'not',
                'number' => 'not',
            ],
            [
                'id' => $recordC,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => 'e84976ac-e9ab-4928-a3dc-c387b66dbaa6',
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::uuid4()->getHex() . '@example.com',
                'password' => 'not',
                'lastName' => 'not',
                'firstName' => 'not',
                'salutation' => 'not',
                'number' => $matchTerm,
            ],
            [
                'id' => $recordD,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => 'e84976ac-e9ab-4928-a3dc-c387b66dbaa6',
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $matchTerm,
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
        $pattern = self::$container->get(SearchTermInterpreter::class)->interpret($matchTerm, Context::createDefaultContext(Defaults::TENANT_ID));
        $queries = $builder->buildScoreQueries($pattern, CustomerDefinition::class, CustomerDefinition::getEntityName());
        $criteria->addQueries($queries);

        $result = $this->repository->searchIds($criteria, Context::createDefaultContext(Defaults::TENANT_ID));

        static::assertCount(4, $result->getIds());

        static::assertGreaterThan(
            $result->getDataFieldOfId($recordA, '_score'),
            $result->getDataFieldOfId($recordB, '_score')
        );

        static::assertGreaterThan(
            $result->getDataFieldOfId($recordD, '_score'),
            $result->getDataFieldOfId($recordC, '_score')
        );

        static::assertGreaterThan(
            $result->getDataFieldOfId($recordA, '_score'),
            $result->getDataFieldOfId($recordC, '_score')
        );
    }
}
