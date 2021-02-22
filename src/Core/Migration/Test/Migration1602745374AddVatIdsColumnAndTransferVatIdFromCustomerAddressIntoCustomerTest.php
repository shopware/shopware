<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Migration1604056363CustomerWishlist;

class Migration1602745374AddVatIdsColumnAndTransferVatIdFromCustomerAddressIntoCustomerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var TestDataCollection
     */
    private $ids;

    public function setUp(): void
    {
        static::markTestSkipped('vatId was removed');
        parent::setUp();

        $this->ids = new TestDataCollection(Context::createDefaultContext());
    }

    public function testNoChanges(): void
    {
        $conn = $this->getContainer()->get(Connection::class);
        $conn->rollBack();
        $expectedProductSchema = $conn->fetchAssoc('SHOW CREATE TABLE `customer`')['Create Table'];

        $migration = new Migration1604056363CustomerWishlist();

        $migration->update($conn);
        $actualProductSchema = $conn->fetchAssoc('SHOW CREATE TABLE `customer`')['Create Table'];
        static::assertSame($expectedProductSchema, $actualProductSchema, 'Schema changed!. Run init again to have clean state');
        $conn->beginTransaction();
    }

    public function testTriggersSet(): void
    {
        $databaseName = substr(parse_url($_SERVER['DATABASE_URL'])['path'], 1);

        $conn = $this->getContainer()->get(Connection::class);
        $updateTrigger = $conn->fetchAll('SHOW TRIGGERS IN ' . $databaseName . ' WHERE `Trigger` = \'customer_address_vat_id_update\'');

        static::assertCount(1, $updateTrigger);

        $insertTrigger = $conn->fetchAll('SHOW TRIGGERS IN ' . $databaseName . ' WHERE `Trigger` = \'customer_address_vat_id_insert\'');

        static::assertCount(1, $insertTrigger);
    }

    public function testInsertNewValueWithCustomerAddressVatId(): void
    {
        $this->createCustomer([
            'defaultShippingAddress' => [
                'vatId' => 'test',
            ],
        ]);

        $customerAddressRepository = $this->getContainer()->get('customer_address.repository');

        $criteria = new Criteria([$this->ids->get('address_id')]);
        $customerAddress = $customerAddressRepository->search($criteria, $this->ids->context)->first();

        $customerRepository = $this->getContainer()->get('customer.repository');

        $criteria = new Criteria([$this->ids->get('customer_id')]);
        $customer = $customerRepository->search($criteria, $this->ids->context)->first();

        static::assertNotNull($customer->getVatIds());
        static::assertIsArray($customer->getVatIds());
        static::assertEquals($customer->getVatIds()[0], $customerAddress->getVatId());
    }

    public function testInsertNewValueWithCustomerAddressVatIdIsNull(): void
    {
        $this->createCustomer();

        $customerAddressRepository = $this->getContainer()->get('customer_address.repository');

        $criteria = new Criteria([$this->ids->get('address_id')]);
        $customerAddress = $customerAddressRepository->search($criteria, $this->ids->context)->first();

        $customerRepository = $this->getContainer()->get('customer.repository');

        $criteria = new Criteria([$this->ids->get('customer_id')]);
        $customer = $customerRepository->search($criteria, $this->ids->context)->first();

        static::assertNull($customer->getVatIds());
        static::assertNull($customerAddress->getVatId());
        static::assertEquals($customer->getVatIds(), $customerAddress->getVatId());
    }

    public function testInsertNewValueWithCustomerVatIds(): void
    {
        $this->createCustomer([
            'vatIds' => ['GR123123123'],
        ]);

        $customerAddressRepository = $this->getContainer()->get('customer_address.repository');

        $criteria = new Criteria([$this->ids->get('address_id')]);
        $customerAddress = $customerAddressRepository->search($criteria, $this->ids->context)->first();

        $customerRepository = $this->getContainer()->get('customer.repository');

        $criteria = new Criteria([$this->ids->get('customer_id')]);
        $customer = $customerRepository->search($criteria, $this->ids->context)->first();

        static::assertNotNull($customer->getVatIds());
        static::assertIsArray($customer->getVatIds());
        static::assertEquals(['GR123123123'], $customer->getVatIds());
        static::assertEquals($customer->getVatIds()[0], $customerAddress->getVatId());
    }

    public function testUpdateCustomerAddressVatIdToNewValue(): void
    {
        $this->createCustomer([
            'defaultShippingAddress' => [
                'vatId' => 'test',
            ],
        ]);

        $customerAddressRepository = $this->getContainer()->get('customer_address.repository');

        $customerAddressRepository->update([[
            'id' => $this->ids->get('address_id'),
            'vatId' => 'AU123123123',
        ]], $this->ids->context);
        $criteria = new Criteria([$this->ids->get('address_id')]);
        $customerAddress = $customerAddressRepository->search($criteria, $this->ids->context)->first();

        $customerRepository = $this->getContainer()->get('customer.repository');

        $criteria = new Criteria([$this->ids->get('customer_id')]);
        $customer = $customerRepository->search($criteria, $this->ids->context)->first();

        static::assertNotNull($customer->getVatIds());
        static::assertIsArray($customer->getVatIds());
        static::assertEquals($customer->getVatIds()[0], $customerAddress->getVatId());
        static::assertEquals(['AU123123123'], $customer->getVatIds());
    }

    public function testUpdateCustomerAddressVatIdNotNullToNull(): void
    {
        $this->createCustomer([
            'defaultShippingAddress' => [
                'vatId' => 'test',
            ],
        ]);

        $customerAddressRepository = $this->getContainer()->get('customer_address.repository');

        $customerAddressRepository->update([[
            'id' => $this->ids->get('address_id'),
            'vatId' => null,
        ]], $this->ids->context);
        $criteria = new Criteria([$this->ids->get('address_id')]);
        $customerAddress = $customerAddressRepository->search($criteria, $this->ids->context)->first();

        $customerRepository = $this->getContainer()->get('customer.repository');

        $criteria = new Criteria([$this->ids->get('customer_id')]);
        $customer = $customerRepository->search($criteria, $this->ids->context)->first();

        static::assertIsArray($customer->getVatIds());
        static::assertEmpty($customer->getVatIds());
        static::assertNull($customerAddress->getVatId());
    }

    public function testUpdateCustomerAddressVatIdNullToNewValue(): void
    {
        $this->createCustomer();

        $customerAddressRepository = $this->getContainer()->get('customer_address.repository');

        $customerAddressRepository->update([[
            'id' => $this->ids->get('address_id'),
            'vatId' => 'AU123123123',
        ]], $this->ids->context);
        $criteria = new Criteria([$this->ids->get('address_id')]);
        $customerAddress = $customerAddressRepository->search($criteria, $this->ids->context)->first();

        $customerRepository = $this->getContainer()->get('customer.repository');

        $criteria = new Criteria([$this->ids->get('customer_id')]);
        $customer = $customerRepository->search($criteria, $this->ids->context)->first();

        static::assertNotNull($customer->getVatIds());
        static::assertIsArray($customer->getVatIds());
        static::assertEquals($customer->getVatIds()[0], $customerAddress->getVatId());
    }

    private function createCustomer($additionalData = []): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $data = [
            'id' => $this->ids->create('customer_id'),
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $this->ids->create('address_id'),
                'firstName' => 'Huy',
                'lastName' => 'Truong',
                'street' => 'DN',
                'city' => 'DN',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
                'company' => 'Test Company',
            ],
            'defaultBillingAddressId' => $this->ids->get('address_id'),
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => $password,
            'firstName' => 'Huy',
            'lastName' => 'Truong',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
            'company' => 'Test Company',
        ];
        $insertData = array_merge_recursive($data, $additionalData);
        $this->getContainer()->get('customer.repository')->create([$insertData], $this->ids->context);
    }
}
