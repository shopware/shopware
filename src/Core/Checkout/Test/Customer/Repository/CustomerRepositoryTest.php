<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('customer-order')]
class CustomerRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepository
     */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('customer.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testGetNoDuplicateMappingTableException(): void
    {
        $id = $this->createCustomer();

        $ids = new IdsCollection();

        $update = [
            'id' => $id,
            'tags' => [
                ['id' => $ids->get('tag-1'), 'name' => 'tag-1'],
                ['id' => $ids->get('tag-2'), 'name' => 'tag-2'],
                ['id' => $ids->get('tag-3'), 'name' => 'tag-3'],
            ],
        ];

        $this->getContainer()->get('customer.repository')
            ->update([$update], Context::createDefaultContext());

        $this->getContainer()->get('customer.repository')
            ->update([$update], Context::createDefaultContext());

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM customer_tag WHERE customer_id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertEquals(3, $count);
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
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => $paymentMethod,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not12345',
                'lastName' => 'not',
                'firstName' => $matchTerm,
                'salutationId' => $salutation,
                'customerNumber' => 'not',
            ],
            [
                'id' => $recordB,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => $paymentMethod,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not12345',
                'lastName' => $matchTerm,
                'firstName' => 'not',
                'salutationId' => $salutation,
                'customerNumber' => 'not',
            ],
            [
                'id' => $recordC,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => $paymentMethod,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not12345',
                'lastName' => 'not',
                'firstName' => 'not',
                'salutationId' => $salutation,
                'customerNumber' => $matchTerm,
            ],
            [
                'id' => $recordD,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => $paymentMethod,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $matchTerm . '@example.com',
                'password' => 'not12345',
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

    public function testDeleteCustomerWithTags(): void
    {
        $customerId = Uuid::randomHex();
        $salutation = $this->getValidSalutationId();
        $this->repository->create([
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'firstName' => 'not',
                    'lastName' => 'not',
                    'city' => 'not',
                    'street' => 'not',
                    'zipcode' => 'not',
                    'salutationId' => $salutation,
                    'country' => ['name' => 'not'],
                ],
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'test@example.com',
                'password' => 'not12345',
                'lastName' => 'not',
                'firstName' => 'test',
                'salutationId' => $salutation,
                'customerNumber' => 'not',
                'tags' => [['name' => 'testTag']],
            ],
        ], Context::createDefaultContext());

        $this->repository->delete([['id' => $customerId]], Context::createDefaultContext());
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $data = [
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'foo@bar.de',
                'password' => 'password12345',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, Context::createDefaultContext());

        return $customerId;
    }
}
