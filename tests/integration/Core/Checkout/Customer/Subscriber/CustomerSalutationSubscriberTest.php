<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\Test\Integration\PaymentHandler\SyncTestPaymentHandler;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerSalutationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private TestDataCollection $ids;

    private EntityRepository $customerRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->connection = KernelLifecycleManager::getConnection();

        $this->customerRepository = $this->getContainer()->get('customer.repository');
    }

    public function testSetDefaultSalutationWithExistingNotSpecifiedSalutation(): void
    {
        $salutations = $this->connection->fetchAllKeyValue('SELECT salutation_key, id FROM salutation');
        static::assertArrayHasKey(SalutationDefinition::NOT_SPECIFIED, $salutations);

        $this->createCustomer();

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->first();

        static::assertNotNull($customer->getSalutationId());
    }

    public function testSetDefaultSalutationToNotSpecifiedWithoutExistingSalutation(): void
    {
        $this->connection->executeStatement(
            '
					DELETE FROM salutation WHERE salutation_key = :salutationKey
				',
            ['salutationKey' => SalutationDefinition::NOT_SPECIFIED]
        );

        $salutations = $this->connection->fetchAllKeyValue('SELECT salutation_key, id FROM salutation');
        static::assertArrayNotHasKey(SalutationDefinition::NOT_SPECIFIED, $salutations);

        $this->createCustomer();

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->first();

        static::assertNull($customer->getSalutationId());
    }

    private function createCustomer(): void
    {
        $customerId = $this->ids->create('customer');
        $addressId = Uuid::randomHex();

        $this->customerRepository->create([
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
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'technicalName' => 'payment_test',
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'availabilityRule' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'cartCartAmount',
                                'value' => [
                                    'operator' => '>=',
                                    'amount' => 0,
                                ],
                            ],
                        ],
                    ],
                    'salesChannels' => [
                        [
                            'id' => TestDefaults::SALES_CHANNEL,
                        ],
                    ],
                ],
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => Uuid::randomHex(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'guest' => false,
                'salutationId' => null,
                'customerNumber' => '12345',
            ],
        ], Context::createDefaultContext());
    }
}
