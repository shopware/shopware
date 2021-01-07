<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @deprecated tag:v6.4.0 - class will be removed in 6.4.0
 */
class VatIdWriteDeprecationTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var TestDataCollection
     */
    private $ids;

    public function setUp(): void
    {
        parent::setUp();

        $this->ids = new TestDataCollection(Context::createDefaultContext());
    }

    /**
     * @dataProvider newFieldVatIdData
     */
    public function testInsertNewFieldCustomerVatIds(array $vatId): void
    {
        $this->createCustomer($vatId);

        $customerAddressRepository = $this->getContainer()->get('customer_address.repository');

        $criteria = new Criteria([$this->ids->get('address_id')]);
        $customerAddress = $customerAddressRepository->search($criteria, $this->ids->context)->first();

        $customerRepository = $this->getContainer()->get('customer.repository');

        $criteria = new Criteria([$this->ids->get('customer_id')]);
        $customer = $customerRepository->search($criteria, $this->ids->context)->first();

        if (empty($vatId)) {
            // Inserting without vat id
            static::assertNull($customer->getVatIds());
            static::assertNull($customerAddress->getVatId());
            static::assertEquals($customer->getVatIds(), $customerAddress->getVatId());
        } else {
            // Inserting with vat id
            static::assertIsArray($customer->getVatIds());
            static::assertNotEmpty($customer->getVatIds());
            static::assertNotNull($customerAddress->getVatId());
            static::assertIsString($customerAddress->getVatId());
            static::assertEquals($customer->getVatIds()[0], $customerAddress->getVatId());
        }
    }

    /**
     * @dataProvider newFieldVatIdData
     */
    public function testUpdateNewFieldCustomerVatIds(array $vatId): void
    {
        $this->createCustomer($vatId);

        $customerRepository = $this->getContainer()->get('customer.repository');

        $customerRepository->update([[
            'id' => $this->ids->get('customer_id'),
            'vatIds' => ['AU123123123'],
        ]], $this->ids->context);
        $criteria = new Criteria([$this->ids->get('customer_id')]);
        $customer = $customerRepository->search($criteria, $this->ids->context)->first();

        $customerAddressRepository = $this->getContainer()->get('customer_address.repository');

        $criteria = new Criteria([$this->ids->get('address_id')]);
        $customerAddress = $customerAddressRepository->search($criteria, $this->ids->context)->first();

        static::assertNotNull($customerAddress->getVatId());
        static::assertIsString($customerAddress->getVatId());
        static::assertEquals('AU123123123', $customerAddress->getVatId());
        static::assertEquals($customer->getVatIds()[0], $customerAddress->getVatId());
    }

    public function testUpdateNewFieldCustomerVatIdsToEmpty(): void
    {
        $this->createCustomer(['vatIds' => ['AU123123123']]);

        $customerRepository = $this->getContainer()->get('customer.repository');

        $customerRepository->update([[
            'id' => $this->ids->get('customer_id'),
            'vatIds' => [],
        ]], $this->ids->context);
        $criteria = new Criteria([$this->ids->get('customer_id')]);
        $customer = $customerRepository->search($criteria, $this->ids->context)->first();

        $customerAddressRepository = $this->getContainer()->get('customer_address.repository');

        $criteria = new Criteria([$this->ids->get('address_id')]);
        $customerAddress = $customerAddressRepository->search($criteria, $this->ids->context)->first();

        static::assertNull($customerAddress->getVatId());
        static::assertEmpty($customerAddress->getVatId());
        static::assertIsArray($customer->getVatIds());
        static::assertEmpty($customer->getVatIds());
    }

    public function newFieldVatIdData(): array
    {
        return [
            'Inserting/updating with vatIds' => [
                'customer vat ids' => [
                    'vatIds' => ['GR123123123'],
                ],
            ],
            'Inserting/updating without vatIds' => [
                [],
            ],
        ];
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
