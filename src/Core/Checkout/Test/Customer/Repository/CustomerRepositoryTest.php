<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

class CustomerRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('customer.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testSearchRanking(): void
    {
        $recordA = Uuid::randomHex();
        $recordB = Uuid::randomHex();
        $recordC = Uuid::randomHex();
        $recordD = Uuid::randomHex();

        $salutation = $this->getValidSalutationId();
        $address = [
            'firstName' => 'not',
            'lastName' => 'not',
            'city' => 'not',
            'street' => 'not',
            'zipcode' => 'not',
            'salutationId' => $salutation,
            'country' => ['name' => 'not'],
        ];

        $matchTerm = Random::getAlphanumericString(20);

        $paymentMethod = $this->getValidPaymentMethodId();
        $records = [
            [
                'id' => $recordA,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => $paymentMethod,
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not',
                'lastName' => 'not',
                'firstName' => $matchTerm,
                'salutationId' => $salutation,
                'customerNumber' => 'not',
            ],
            [
                'id' => $recordB,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => $paymentMethod,
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not',
                'lastName' => $matchTerm,
                'firstName' => 'not',
                'salutationId' => $salutation,
                'customerNumber' => 'not',
            ],
            [
                'id' => $recordC,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => $paymentMethod,
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not',
                'lastName' => 'not',
                'firstName' => 'not',
                'salutationId' => $salutation,
                'customerNumber' => $matchTerm,
            ],
            [
                'id' => $recordD,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => $paymentMethod,
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $matchTerm . '@example.com',
                'password' => 'not',
                'lastName' => 'not',
                'firstName' => 'not',
                'salutationId' => $salutation,
                'customerNumber' => 'not',
            ],
        ];

        $this->repository->create($records, Context::createDefaultContext());

        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $definition = $this->getContainer()->get(CustomerDefinition::class);
        $builder = $this->getContainer()->get(EntityScoreQueryBuilder::class);
        $pattern = $this->getContainer()->get(SearchTermInterpreter::class)->interpret($matchTerm);
        $queries = $builder->buildScoreQueries($pattern, $definition, $definition->getEntityName(), $context);
        $criteria->addQuery(...$queries);

        $result = $this->repository->searchIds($criteria, $context);

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
