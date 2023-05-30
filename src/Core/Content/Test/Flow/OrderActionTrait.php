<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[Package('business-ops')]
trait OrderActionTrait
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private ?EntityRepository $customerRepository = null;

    private function createCustomerAndLogin(?string $email = null, ?string $password = null): void
    {
        $email ??= Uuid::randomHex() . '@example.com';
        $password ??= 'shopware';
        $this->prepareCustomer($password, $email);

        $this->login($email, $password);
    }

    /**
     * @param array<string, mixed> $additionalData
     */
    private function prepareCustomer(string $password, ?string $email = null, array $additionalData = []): void
    {
        static::assertNotNull($this->customerRepository);

        $this->customerRepository->create([
            array_merge([
                'id' => $this->ids->create('customer'),
                'salesChannelId' => $this->ids->get('sales-channel'),
                'defaultShippingAddress' => [
                    'id' => $this->ids->create('address'),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
                ],
                'defaultBillingAddressId' => $this->ids->get('address'),
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
                'vatIds' => ['DE123456789'],
                'company' => 'Test',
            ], $additionalData),
        ], Context::createDefaultContext());
    }

    private function login(?string $email = null, ?string $password = null): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    private function prepareProductTest(): void
    {
        $this->getContainer()->get('product.repository')->create([
            [
                'id' => $this->ids->create('p1'),
                'productNumber' => $this->ids->get('p1'),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());
    }

    private function submitOrder(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ]
            );

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
                [
                    'affiliateCode' => 'test affiliate code',
                ]
            );
    }

    private function cancelOrder(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/state/cancel',
                [
                    'orderId' => $this->ids->get('order'),
                ]
            );
    }

    /**
     * @param array<string, mixed> $additionalData
     */
    private function createOrder(string $customerId, array $additionalData = []): void
    {
        $this->getContainer()->get('order.repository')->create([
            array_merge([
                'id' => $this->ids->create('order'),
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'orderCustomer' => [
                    'customerId' => $customerId,
                    'email' => 'test@example.com',
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                ],
                'orderNumber' => Uuid::randomHex(),
                'stateId' => $this->getStateMachineState(),
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1.0,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'billingAddressId' => $billingAddressId = Uuid::randomHex(),
                'addresses' => [
                    [
                        'id' => $billingAddressId,
                        'salutationId' => $this->getValidSalutationId(),
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                        'street' => 'Ebbinghoff 10',
                        'zipcode' => '48624',
                        'city' => 'Schöppingen',
                        'countryId' => $this->getValidCountryId(),
                    ],
                ],
                'lineItems' => [
                    [
                        'id' => $this->ids->create('line-item'),
                        'identifier' => $this->ids->create('line-item'),
                        'quantity' => 1,
                        'label' => 'label',
                        'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                ],
                'deliveries' => [
                    [
                        'id' => $this->ids->create('delivery'),
                        'shippingOrderAddressId' => $this->ids->create('shipping-address'),
                        'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
                        'stateId' => $this->getStateId('open', 'order_delivery.state'),
                        'trackingCodes' => [],
                        'shippingDateEarliest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'shippingDateLatest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'positions' => [
                            [
                                'id' => $this->ids->create('position'),
                                'orderLineItemId' => $this->ids->create('line-item'),
                                'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                            ],
                        ],
                    ],
                ],
                'context' => '{}',
                'payload' => '{}',
            ], $additionalData),
        ], Context::createDefaultContext());
    }

    private function getStateId(string $state, string $machine): string
    {
        return $this->getContainer()->get(Connection::class)
            ->fetchOne('
                SELECT LOWER(HEX(state_machine_state.id))
                FROM state_machine_state
                    INNER JOIN  state_machine
                    ON state_machine.id = state_machine_state.state_machine_id
                    AND state_machine.technical_name = :machine
                WHERE state_machine_state.technical_name = :state
            ', [
                'state' => $state,
                'machine' => $machine,
            ]) ?: '';
    }

    private function createCustomField(string $name, string $entity, string $type = CustomFieldTypes::SELECT): string
    {
        $customFieldId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();
        $data = [
            'id' => $customFieldId,
            'name' => $name,
            'type' => $type,
            'customFieldSetId' => $customFieldSetId,
            'config' => [
                'componentName' => 'sw-field',
                'customFieldPosition' => 1,
                'customFieldType' => $type,
                'type' => $type,
                'label' => [
                    'en-GB' => 'lorem_ipsum',
                    'de-DE' => 'lorem_ipsum',
                ],
            ],
            'customFieldSet' => [
                'id' => $customFieldSetId,
                'name' => 'Custom Field Set',
                'relations' => [[
                    'id' => Uuid::randomHex(),
                    'customFieldSetId' => $customFieldSetId,
                    'entityName' => $entity,
                ]],
            ],
        ];

        $this->getContainer()->get('custom_field.repository')
            ->create([$data], Context::createDefaultContext());

        return $customFieldId;
    }
}
