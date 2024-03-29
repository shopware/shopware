<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Document\DocumentEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1696300511AddDocumentNumberToDocumentEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1696300511AddDocumentNumberToDocumentEntity::class)]
class Migration1696300511AddDocumentNumberToDocumentEntityTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<DocumentCollection>
     */
    private EntityRepository $documentRepository;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private Context $context;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->documentRepository = $this->getContainer()->get('document.repository');
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->context = Context::createDefaultContext();
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement(
                'ALTER TABLE `document` DROP COLUMN `document_number`;'
            );
        } catch (\Throwable) {
        }
    }

    public function testUpdate(): void
    {
        $migration = new Migration1696300511AddDocumentNumberToDocumentEntity();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $columnDefinition = $this->getColumnDefinition('document', 'document_number');

        static::assertIsArray($columnDefinition);
        static::assertEquals('document_number', $columnDefinition['Field']);
        static::assertEquals('YES', $columnDefinition['Null']);
        static::assertEquals('varchar(255)', $columnDefinition['Type']);
    }

    public function testGenerateDocumentNumber(): void
    {
        $migration = new Migration1696300511AddDocumentNumberToDocumentEntity();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $documentId = Uuid::randomHex();

        $this->createDocument($documentId);

        $criteria = new Criteria();
        $criteria->setIds([$documentId]);

        /** @var DocumentEntity $document */
        $document = $this->documentRepository->search($criteria, $this->context)->first();

        static::assertEquals('123456789', $document->getConfig()['documentNumber']);
        static::assertSame($document->getDocumentNumber(), $document->getConfig()['documentNumber']);

        $this->documentRepository->update([[
            'id' => $document->getId(),
            'config' => [
                'id' => Uuid::randomHex(),
                'name' => 'invoice',
                'custom' => [
                    'invoiceNumber' => '2000',
                ],
            ],
        ]], $this->context);

        /** @var DocumentEntity $document */
        $document = $this->documentRepository->search($criteria, $this->context)->first();

        static::assertArrayNotHasKey('documentNumber', $document->getConfig());
        static::assertNull($document->getDocumentNumber());
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getColumnDefinition(string $table, string $column): array|false
    {
        return $this->connection->fetchAssociative(
            'SHOW COLUMNS FROM ' . $table . ' WHERE Field = :column',
            ['column' => $column],
        );
    }

    private function createDocument(string $documentId): void
    {
        $documentTypeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(document_type.id)) FROM `document_type` WHERE `technical_name` = :technicalName;',
            ['technicalName' => 'invoice']
        );

        $order = $this->createOrder();

        $this->documentRepository->create([
            [
                'id' => $documentId,
                'documentTypeId' => $documentTypeId,
                'fileType' => 'pdf',
                'orderId' => $order->getId(),
                'orderVersionId' => Defaults::LIVE_VERSION,
                'config' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'invoice',
                    'custom' => [
                        'invoiceNumber' => '1000',
                    ],
                    'documentNumber' => '123456789',
                ],
                'sent' => false,
                'static' => false,
                'deepLinkCode' => Uuid::randomHex(),
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @throws \JsonException
     */
    private function createOrder(): OrderEntity
    {
        // create product
        $productId = Uuid::randomHex();
        $product = $this->getProductData($productId);

        /** @var EntityRepository $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create([$product], Context::createDefaultContext());

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, Context::createDefaultContext())[0];

        $orderData['lineItems'][0]['productId'] = $productId;

        $this->orderRepository->create([$orderData], Context::createDefaultContext());

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search(new Criteria(), Context::createDefaultContext())->first();

        static::assertNotNull($order);

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function getProductData(string $productId): array
    {
        return [
            'id' => $productId,
            'stock' => 101,
            'productNumber' => Uuid::randomHex(),
            'active' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'test product',
                ],
            ],
            'tax' => [
                'name' => '19%',
                'taxRate' => 19.0,
            ],
            'price' => [
                Defaults::CURRENCY => [
                    'gross' => 1.111,
                    'net' => 1.011,
                    'linked' => true,
                    'currencyId' => Defaults::CURRENCY,
                    'listPrice' => [
                        'gross' => 1.111,
                        'net' => 1.011,
                        'linked' => false,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
            ],
        ];
    }

    /**
     * @throws \JsonException
     *
     * @return array<int, array<string, mixed>>
     */
    private function getOrderData(string $orderId, Context $context): array
    {
        $orderCustomerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $orderLineItemId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $customerId = Uuid::randomHex();
        $orderNumber = Uuid::randomHex();

        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $salesChannelRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('id', TestDefaults::SALES_CHANNEL)),
            $context,
        )->first();

        $salutationRepository = $this->getContainer()->get('salutation.repository');

        /** @var SalesChannelEntity $salutation */
        $salutation = $salutationRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('salutationKey', 'mr')),
            $context,
        )->first();

        $paymentMethodId = $salesChannel->getPaymentMethodId();
        $shippingMethodId = $salesChannel->getShippingMethodId();
        $salutationId = $salutation->getId();
        $countryId = Uuid::randomHex();

        return [
            [
                'id' => $orderId,
                'orderNumber' => $orderNumber,
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'stateId' => Uuid::randomHex(),
                'versionId' => Defaults::LIVE_VERSION,
                'paymentMethodId' => $paymentMethodId,
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'orderDateTime' => '2019-04-01 08:36:43.267',
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'deliveries' => [
                    [
                        'stateId' => Uuid::randomHex(),
                        'shippingMethodId' => $shippingMethodId,
                        'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'shippingDateEarliest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
                        'shippingDateLatest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
                        'shippingOrderAddress' => [
                            'salutationId' => $salutationId,
                            'firstName' => 'Floy',
                            'lastName' => 'Glover',
                            'zipcode' => '59438-0403',
                            'city' => 'Stellaberg',
                            'street' => 'street',
                            'country' => [
                                'name' => 'kasachstan',
                                'id' => $countryId,
                            ],
                        ],
                        'trackingCodes' => [
                            'CODE-1',
                            'CODE-2',
                        ],
                        'positions' => [
                            [
                                'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                                'orderLineItemId' => $orderLineItemId,
                            ],
                        ],
                    ],
                ],
                'lineItems' => [
                    [
                        'id' => $orderLineItemId,
                        'identifier' => 'test',
                        'quantity' => 1,
                        'type' => 'test',
                        'label' => 'test',
                        'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection()),
                        'priority' => 100,
                        'good' => true,
                    ],
                ],
                'orderCustomerId' => $orderCustomerId,
                'orderCustomer' => [
                    'id' => $orderCustomerId,
                    'email' => 'test@example.com',
                    'firstName' => 'Noe',
                    'lastName' => 'Hill',
                    'salutationId' => $salutationId,
                    'title' => 'Doc',
                    'customerNumber' => 'Test',
                    'orderVersionId' => Defaults::LIVE_VERSION,
                    'customer' => [
                        'id' => $customerId,
                        'email' => 'test@example.com',
                        'firstName' => 'Noe',
                        'lastName' => 'Hill',
                        'salutationId' => $salutationId,
                        'title' => 'Doc',
                        'customerNumber' => 'Test',
                        'guest' => true,
                        'group' => ['name' => 'testse2323'],
                        'defaultPaymentMethodId' => $paymentMethodId,
                        'salesChannelId' => TestDefaults::SALES_CHANNEL,
                        'defaultBillingAddressId' => $addressId,
                        'defaultShippingAddressId' => $addressId,
                        'addresses' => [
                            [
                                'id' => $addressId,
                                'salutationId' => $salutationId,
                                'firstName' => 'Floy',
                                'lastName' => 'Glover',
                                'zipcode' => '59438-0403',
                                'city' => 'Stellaberg',
                                'street' => 'street',
                                'countryStateId' => $countryStateId,
                                'country' => [
                                    'name' => 'kasachstan',
                                    'id' => $countryId,
                                    'states' => [
                                        [
                                            'id' => $countryStateId,
                                            'name' => 'oklahoma',
                                            'shortCode' => 'OH',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'billingAddressId' => $addressId,
                'addresses' => [
                    [
                        'salutationId' => $salutationId,
                        'firstName' => 'Floy',
                        'lastName' => 'Glover',
                        'zipcode' => '59438-0403',
                        'city' => 'Stellaberg',
                        'street' => 'street',
                        'countryId' => $countryId,
                        'id' => $addressId,
                    ],
                ],
            ],
        ];
    }
}
