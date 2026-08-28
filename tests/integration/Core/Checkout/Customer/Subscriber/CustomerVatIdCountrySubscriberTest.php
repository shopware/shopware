<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * The store-api register and change-profile routes are not the only writers of `customer.vatIds`:
 * the Administration, the Admin API, the Sync API, imports and plugins write them through the DAL
 * directly, and all of them have to end up with the same `vatIdCountryId`.
 *
 * @internal
 */
#[Package('checkout')]
class CustomerVatIdCountrySubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->customerRepository = static::getContainer()->get('customer.repository');
    }

    public function testCreatingACustomerThroughTheDalResolvesTheMemberState(): void
    {
        $id = $this->createCustomer(['NL123456789B01']);

        static::assertSame($this->getCountryIdByIso('NL'), $this->getVatIdCountryId($id));
    }

    public function testUpdatingTheVatIdsThroughTheDalUpdatesTheMemberState(): void
    {
        $id = $this->createCustomer(['NL123456789B01']);

        $this->customerRepository->update(
            [['id' => $id, 'vatIds' => ['ATU12345678']]],
            Context::createDefaultContext()
        );

        static::assertSame($this->getCountryIdByIso('AT'), $this->getVatIdCountryId($id));
    }

    public function testClearingTheVatIdsThroughTheDalClearsTheMemberState(): void
    {
        $id = $this->createCustomer(['NL123456789B01']);

        $this->customerRepository->update(
            [['id' => $id, 'vatIds' => null]],
            Context::createDefaultContext()
        );

        static::assertNull($this->getVatIdCountryId($id));
    }

    public function testAVatIdOfNoMemberStateStoresNoCountry(): void
    {
        $id = $this->createCustomer(['CHE123456789']);

        static::assertNull($this->getVatIdCountryId($id));
    }

    public function testWritesThatDoNotTouchTheVatIdsKeepTheMemberState(): void
    {
        $id = $this->createCustomer(['NL123456789B01']);

        $this->customerRepository->update(
            [['id' => $id, 'lastName' => 'Musterfrau']],
            Context::createDefaultContext()
        );

        static::assertSame($this->getCountryIdByIso('NL'), $this->getVatIdCountryId($id));
    }

    /**
     * @param list<string> $vatIds
     */
    private function createCustomer(array $vatIds): string
    {
        $id = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerRepository->create([[
            'id' => $id,
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
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => Uuid::randomHex(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'guest' => false,
            'customerNumber' => '12345',
            'vatIds' => $vatIds,
        ]], Context::createDefaultContext());

        return $id;
    }

    private function getVatIdCountryId(string $customerId): ?string
    {
        $customer = $this->customerRepository
            ->search(new Criteria([$customerId]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($customer);

        return $customer->getVatIdCountryId();
    }

    private function getCountryIdByIso(string $iso): string
    {
        $countryId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `country` WHERE `iso` = :iso',
            ['iso' => $iso]
        );
        static::assertIsString($countryId);

        return $countryId;
    }
}
