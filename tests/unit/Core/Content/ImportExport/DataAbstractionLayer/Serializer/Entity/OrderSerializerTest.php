<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\OrderSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\OrderSerializer
 */
#[Package('system-settings')]
class OrderSerializerTest extends TestCase
{
    private OrderSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new OrderSerializer();
        $this->serializer->setRegistry($this->createMock(SerializerRegistry::class));
    }

    public function testSupports(): void
    {
        static::assertTrue($this->serializer->supports('order'));
        static::assertFalse($this->serializer->supports('not_order'));
    }

    /**
     * @dataProvider serializeDataProvider
     *
     * @param array<mixed>|Struct|null $entity
     * @param array<mixed> $expected
     */
    public function testSerialize($entity, array $expected): void
    {
        $logEntity = new ImportExportLogEntity();
        $logEntity->setId(Uuid::randomHex());
        $config = Config::fromLog($logEntity);
        $definition = new OrderDefinition();
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));

        $result = iterator_to_array($this->serializer->serialize($config, $definition, $entity));

        static::assertEquals($expected, $result);
    }

    /**
     * @return iterable<string, array{entity: Struct|array<mixed>|null, expected: array<string, mixed>}>
     */
    public static function serializeDataProvider(): iterable
    {
        yield 'without entity' => [
            'entity' => null,
            'expected' => [],
        ];

        yield 'with array record' => [
            'entity' => [
                'orderNumber' => 'NUM-1',
            ],
            'expected' => [
                'orderNumber' => 'NUM-1',
            ],
        ];

        yield 'with entity' => [
            'entity' => self::createOrderEntity(),
            'expected' => self::getExpected(),
        ];

        yield 'with order empty line items' => [
            'entity' => self::createOrderEntity([
                'lineItems' => new OrderLineItemCollection(),
            ]),
            'expected' => self::getExpected(),
        ];

        yield 'with order line items' => [
            'entity' => self::createOrderEntity([
                'lineItems' => self::createLineItems(),
            ]),
            'expected' => self::getExpected([
                'lineItems' => '3x |2x ',
            ]),
        ];

        yield 'with order empty deliveries' => [
            'entity' => self::createOrderEntity([
                'deliveries' => [],
            ]),
            'expected' => self::getExpected([
                'deliveries' => [],
            ]),
        ];

        yield 'with order deliveries' => [
            'entity' => self::createOrderEntity([
                'deliveries' => self::createDeliveries(),
            ]),
            'expected' => self::getExpected([
                'deliveries' => [
                    'extensions' => [],
                    '_uniqueIdentifier' => 'delivery-1',
                    'versionId' => null,
                    'translated' => [],
                    'createdAt' => null,
                    'updatedAt' => null,
                    'orderId' => null,
                    'shippingOrderAddressId' => null,
                    'shippingMethodId' => 'shipping-method-id',
                    'trackingCodes' => 'CODE-1|CODE-2',
                    'shippingDateEarliest' => null,
                    'shippingDateLatest' => null,
                    'shippingCosts' => [
                        'unitPrice' => 1,
                        'quantity' => 1,
                        'totalPrice' => 1,
                        'calculatedTaxes' => [],
                        'taxRules' => [],
                        'referencePrice' => null,
                        'listPrice' => null,
                        'regulationPrice' => null,
                        'extensions' => [],
                    ],
                    'shippingOrderAddress' => [
                        'city' => 'billing-address-city',
                        'countryId' => 'billing-address-country-id',
                        'firstName' => 'billing-address-first-name',
                        'lastName' => 'billing-address-last-name',
                        'salutationId' => 'billing-address-salutation-id',
                        'street' => 'billing-address-street',
                        'zipcode' => 'billing-address-zipcode',
                        'extensions' => [],
                        '_uniqueIdentifier' => null,
                        'versionId' => null,
                        'translated' => [],
                        'createdAt' => null,
                        'updatedAt' => null,
                        'countryStateId' => null,
                        'company' => null,
                        'department' => null,
                        'title' => null,
                        'vatId' => null,
                        'phoneNumber' => null,
                        'additionalAddressLine1' => null,
                        'additionalAddressLine2' => null,
                        'country' => null,
                        'countryState' => null,
                        'order' => null,
                        'salutation' => null,
                        'orderDeliveries' => null,
                        'orderId' => null,
                        'id' => null,
                        'customFields' => null,
                        'orderVersionId' => null,
                    ],
                    'stateId' => '',
                    'stateMachineState' => null,
                    'shippingMethod' => null,
                    'order' => null,
                    'positions' => [],
                    'id' => null,
                    'customFields' => null,
                    'orderVersionId' => null,
                    'shippingOrderAddressVersionId' => null,
                ],
            ]),
        ];

        yield 'with order with line items and deliveries' => [
            'entity' => self::createOrderEntity([
                'lineItems' => self::createLineItems(),
                'deliveries' => self::createDeliveries(),
            ]),
            'expected' => self::getExpected([
                'lineItems' => '3x |2x ',
                'deliveries' => [
                    'extensions' => [],
                    '_uniqueIdentifier' => 'delivery-1',
                    'versionId' => null,
                    'translated' => [],
                    'createdAt' => null,
                    'updatedAt' => null,
                    'orderId' => null,
                    'shippingOrderAddressId' => null,
                    'shippingMethodId' => 'shipping-method-id',
                    'trackingCodes' => 'CODE-1|CODE-2',
                    'shippingDateEarliest' => null,
                    'shippingDateLatest' => null,
                    'shippingCosts' => [
                        'unitPrice' => 1,
                        'quantity' => 1,
                        'totalPrice' => 1,
                        'calculatedTaxes' => [],
                        'taxRules' => [],
                        'referencePrice' => null,
                        'listPrice' => null,
                        'regulationPrice' => null,
                        'extensions' => [],
                    ],
                    'shippingOrderAddress' => [
                        'city' => 'billing-address-city',
                        'countryId' => 'billing-address-country-id',
                        'firstName' => 'billing-address-first-name',
                        'lastName' => 'billing-address-last-name',
                        'salutationId' => 'billing-address-salutation-id',
                        'street' => 'billing-address-street',
                        'zipcode' => 'billing-address-zipcode',
                        'extensions' => [],
                        '_uniqueIdentifier' => null,
                        'versionId' => null,
                        'translated' => [],
                        'createdAt' => null,
                        'updatedAt' => null,
                        'countryStateId' => null,
                        'company' => null,
                        'department' => null,
                        'title' => null,
                        'vatId' => null,
                        'phoneNumber' => null,
                        'additionalAddressLine1' => null,
                        'additionalAddressLine2' => null,
                        'country' => null,
                        'countryState' => null,
                        'order' => null,
                        'salutation' => null,
                        'orderDeliveries' => null,
                        'orderId' => null,
                        'id' => null,
                        'customFields' => null,
                        'orderVersionId' => null,
                    ],
                    'stateId' => '',
                    'stateMachineState' => null,
                    'shippingMethod' => null,
                    'order' => null,
                    'positions' => [],
                    'id' => null,
                    'customFields' => null,
                    'orderVersionId' => null,
                    'shippingOrderAddressVersionId' => null,
                ],
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createOrderEntity(array $data = []): OrderEntity
    {
        $rawData = [
            'price' => [
                'netPrice' => 0,
                'totalPrice' => 0,
                'calculatedTaxes' => [],
                'taxRules' => [],
                'positionPrice' => 0,
                'taxStatus' => 'gross',
                'rawTotal' => 0,
                'extensions' => [],
            ],
            'shippingCosts' => [
                'unitPrice' => 0,
                'quantity' => 1,
                'totalPrice' => 0,
                'calculatedTaxes' => [],
                'taxRules' => [],
                'referencePrice' => null,
                'listPrice' => null,
                'regulationPrice' => null,
                'extensions' => [],
            ],
            'currencyId' => '',
            'currencyFactor' => 0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'customerComment' => null,
            'affiliateCode' => null,
            'campaignCode' => null,
            'itemRounding' => [],
            'totalRounding' => [],
            'orderCustomer' => [
                'company' => null,
                'customFields' => null,
                'customerId' => 'customer-id',
                'customerNumber' => 'customer-number',
                'email' => 'customer-email',
                'firstName' => 'customer-first-name',
                'lastName' => 'customer-last-name',
                'remoteAddress' => null,
                'salutationId' => 'customer-salutation-id',
                'title' => null,
                'vatIds' => null,
            ],
            'transactions' => [],
            'orderNumber' => '10000',
            'ruleIds' => [],
            'addresses' => [
                [
                    'city' => 'billing-address-city',
                    'countryId' => 'billing-address-country-id',
                    'firstName' => 'billing-address-first-name',
                    'lastName' => 'billing-address-last-name',
                    'salutationId' => 'billing-address-salutation-id',
                    'street' => 'billing-address-street',
                    'zipcode' => 'billing-address-zipcode',
                ],
            ],
            'billingAddressVersionId' => null,
        ];

        $data = array_merge_recursive($rawData, $data);

        return (new OrderEntity())->assign($data);
    }

    private static function createLineItems(): OrderLineItemCollection
    {
        $lineItem1 = (new OrderLineItemEntity())->assign([
            '_uniqueIdentifier' => 'line-item-id-1',
            'identifier' => 'line-item-id-1',
            'quantity' => 3,
            'type' => 'line-item-type-1',
            'label' => 'line-item-label-1',
            'good' => true,
            'removable' => false,
            'stackable' => false,
            'states' => [],
            'position' => 1,
            'price' => [
                'unitPrice' => 1,
                'quantity' => 1,
                'totalPrice' => 1,
                'calculatedTaxes' => [],
                'taxRules' => [],
                'referencePrice' => null,
                'listPrice' => null,
                'regulationPrice' => null,
                'extensions' => [],
            ],
            'payload' => [],
        ]);

        $lineItem2 = (new OrderLineItemEntity())->assign([
            '_uniqueIdentifier' => 'line-item-id-2',
            'identifier' => 'line-item-id-2',
            'quantity' => 2,
            'type' => 'line-item-type-2',
            'label' => 'line-item-label-2',
            'good' => true,
            'removable' => false,
            'stackable' => false,
            'states' => [],
            'position' => 2,
            'price' => [
                'unitPrice' => 1,
                'quantity' => 1,
                'totalPrice' => 1,
                'calculatedTaxes' => [],
                'taxRules' => [],
                'referencePrice' => null,
                'listPrice' => null,
                'regulationPrice' => null,
                'extensions' => [],
            ],
            'payload' => [],
        ]);

        return new OrderLineItemCollection([$lineItem1, $lineItem2]);
    }

    /**
     * @param array<string, mixed> $overrided
     *
     * @return array<string, mixed>
     */
    private static function getExpected(array $overrided = []): array
    {
        return array_merge([
            'id' => null,
            'updatedBy' => null,
            'updatedById' => null,
            'customFields' => null,
            '_uniqueIdentifier' => null,
            'translated' => [],
            'extensions' => [],
            'versionId' => null,
            'createdAt' => null,
            'updatedAt' => null,
            'shippingTotal' => null,
            'currency' => null,
            'billingAddressId' => null,
            'orderDateTime' => null,
            'orderDate' => null,
            'amountTotal' => null,
            'amountNet' => null,
            'positionPrice' => null,
            'taxStatus' => null,
            'languageId' => null,
            'language' => null,
            'salesChannel' => null,
            'billingAddress' => null,
            'deliveries' => null,
            'lineItems' => null,
            'deepLinkCode' => null,
            'autoIncrement' => null,
            'stateMachineState' => null,
            'stateId' => null,
            'documents' => null,
            'tags' => null,
            'createdById' => null,
            'createdBy' => null,
            'price' => [
                'netPrice' => 0,
                'totalPrice' => 0,
                'calculatedTaxes' => [],
                'taxRules' => [],
                'positionPrice' => 0,
                'taxStatus' => 'gross',
                'rawTotal' => 0,
                'extensions' => [],
            ],
            'shippingCosts' => [
                'unitPrice' => 0,
                'quantity' => 1,
                'totalPrice' => 0,
                'calculatedTaxes' => [],
                'taxRules' => [],
                'referencePrice' => null,
                'listPrice' => null,
                'regulationPrice' => null,
                'extensions' => [],
            ],
            'currencyId' => '',
            'currencyFactor' => 0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'customerComment' => null,
            'affiliateCode' => null,
            'campaignCode' => null,
            'itemRounding' => [],
            'totalRounding' => [],
            'orderCustomer' => [
                'company' => null,
                'customFields' => null,
                'customerId' => 'customer-id',
                'customerNumber' => 'customer-number',
                'email' => 'customer-email',
                'firstName' => 'customer-first-name',
                'lastName' => 'customer-last-name',
                'remoteAddress' => null,
                'salutationId' => 'customer-salutation-id',
                'title' => null,
                'vatIds' => null,
            ],
            'transactions' => [],
            'orderNumber' => '10000',
            'ruleIds' => [],
            'addresses' => [
                [
                    'city' => 'billing-address-city',
                    'countryId' => 'billing-address-country-id',
                    'firstName' => 'billing-address-first-name',
                    'lastName' => 'billing-address-last-name',
                    'salutationId' => 'billing-address-salutation-id',
                    'street' => 'billing-address-street',
                    'zipcode' => 'billing-address-zipcode',
                ],
            ],
            'billingAddressVersionId' => null,
            'source' => null,
        ], $overrided);
    }

    private static function createDeliveries(): OrderDeliveryCollection
    {
        $delivery1 = (new OrderDeliveryEntity())->assign([
            '_uniqueIdentifier' => 'delivery-1',
            'positions' => [],
            'shippingCosts' => [
                'calculatedTaxes' => [],
                'extensions' => [],
                'listPrice' => null,
                'quantity' => 1,
                'referencePrice' => null,
                'regulationPrice' => null,
                'taxRules' => [],
                'totalPrice' => 1,
                'unitPrice' => 1,
            ],
            'trackingCodes' => ['CODE-1', 'CODE-2'],
            'shippingMethodId' => 'shipping-method-id',
            'shippingOrderAddress' => (new OrderAddressEntity())->assign([
                'city' => 'billing-address-city',
                'countryId' => 'billing-address-country-id',
                'firstName' => 'billing-address-first-name',
                'lastName' => 'billing-address-last-name',
                'salutationId' => 'billing-address-salutation-id',
                'street' => 'billing-address-street',
                'zipcode' => 'billing-address-zipcode',
            ]),
            'stateId' => '',
        ]);

        $delivery2 = (new OrderDeliveryEntity())->assign([
            '_uniqueIdentifier' => 'delivery-2',
            'positions' => [],
            'shippingCosts' => [
                'calculatedTaxes' => [],
                'extensions' => [],
                'listPrice' => null,
                'quantity' => 1,
                'referencePrice' => null,
                'regulationPrice' => null,
                'taxRules' => [],
                'totalPrice' => 1,
                'unitPrice' => 1,
            ],
            'trackingCodes' => ['CODE-3', 'CODE-4'],
            'shippingMethodId' => 'shipping-method-id',
            'shippingOrderAddress' => (new OrderAddressEntity())->assign([
                'city' => 'billing-address-city',
                'countryId' => 'billing-address-country-id',
                'firstName' => 'billing-address-first-name',
                'lastName' => 'billing-address-last-name',
                'salutationId' => 'billing-address-salutation-id',
                'street' => 'billing-address-street',
                'zipcode' => 'billing-address-zipcode',
            ]),
            'stateId' => '',
        ]);

        return new OrderDeliveryCollection([$delivery1, $delivery2]);
    }
}
