<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1736339669MigrationDocumentWithMultipleMedia;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(Migration1736339669MigrationDocumentWithMultipleMedia::class)]
class Migration1736339669MigrationDocumentWithMultipleMediaTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
    }

    public function testMigration(): void
    {
        $this->createDocument();

        /** @var array{document_id: string, media_id: string}|null $document */
        $document = $this->connection->fetchAssociative(
            'SELECT document_media.document_id AS document_id, document_media.media_id as media_id FROM `document` LEFT JOIN `document_media` ON `document`.`id` = `document_media`.`document_id` WHERE `document`.`id` = :id',
            ['id' => Uuid::fromHexToBytes($this->ids->get('document'))],
        );

        static::assertNotNull($document);
        static::assertNull($document['media_id']);
        static::assertNull($document['document_id']);

        $migration = new Migration1736339669MigrationDocumentWithMultipleMedia();
        $migration->update($this->connection);
        $migration->update($this->connection);

        /** @var array{document_id: string, media_id: string}|null $document */
        $document = $this->connection->fetchAssociative(
            'SELECT document_media.document_id AS document_id, document_media.media_id as media_id FROM `document` LEFT JOIN `document_media` ON `document`.`id` = `document_media`.`document_id` WHERE `document`.`id` = :id',
            ['id' => Uuid::fromHexToBytes($this->ids->get('document'))],
        );

        static::assertNotNull($document);
        static::assertNotNull($document['media_id']);
        static::assertNotNull($document['document_id']);

        static::assertSame(Uuid::fromHexToBytes($this->ids->get('media')), $document['media_id']);
        static::assertSame(Uuid::fromHexToBytes($this->ids->get('document')), $document['document_id']);
    }

    private function createDocument(): void
    {
        $documentTypeId = $this->connection->fetchOne('SELECT * FROM document_type LIMIT 1');
        static::assertNotNull($documentTypeId);

        $this->createOrder($this->ids);

        $this->connection->insert('media', [
            'id' => Uuid::fromHexToBytes($this->ids->create('media')),
            'file_name' => 'invoice',
            'file_extension' => 'pdf',
            'mime_type' => 'image/jpeg',
            'file_size' => 12345,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('document', [
            'id' => Uuid::fromHexToBytes($this->ids->create('document')),
            'document_type_id' => $documentTypeId,
            'file_type' => 'pdf',
            'order_id' => Uuid::fromHexToBytes($this->ids->get('order')),
            'order_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'deep_link_code' => '12345',
            'document_media_file_id' => Uuid::fromHexToBytes($this->ids->get('media')),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createOrder(IdsCollection $ids): void
    {
        $customer = (new CustomerBuilder($ids, '10000'))
            ->add('guest', true)
            ->add('createdAt', new \DateTime('- 25 hours'))->build();

        $data = [
            'id' => $ids->create('order'),
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderNumber' => Uuid::randomHex(),
            'billingAddressId' => $ids->create('billing-address'),
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'orderDateTime' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'currencyFactor' => 1,
            'stateId' => $this->getStateId('open', 'order.state'),
            'price' => new CartPrice(200, 200, 200, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'ruleIds' => [$ids->get('rule')],
            'orderCustomer' => [
                'id' => $ids->get('customer'),
                'salutationId' => $this->getValidSalutationId(),
                'email' => 'test',
                'firstName' => 'test',
                'lastName' => 'test',
                'customer' => $customer,
            ],
            'addresses' => [
                [
                    'id' => $ids->create('billing-address'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'asd',
                    'lastName' => 'asd',
                    'street' => 'asd',
                    'zipcode' => 'asd',
                    'city' => 'asd',
                ],
                [
                    'id' => $ids->create('shipping-address'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'asd',
                    'lastName' => 'asd',
                    'street' => 'asd',
                    'zipcode' => 'asd',
                    'city' => 'asd',
                ],
            ],
            'lineItems' => [
                [
                    'id' => $ids->create('line-item'),
                    'identifier' => $ids->create('line-item'),
                    'quantity' => 1,
                    'label' => 'label',
                    'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
                    'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                ],
            ],
            'deliveries' => [
                [
                    'id' => $ids->create('delivery'),
                    'shippingOrderAddressId' => $ids->create('shipping-address'),
                    'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
                    'stateId' => $this->getStateId('open', 'order_delivery.state'),
                    'trackingCodes' => [],
                    'shippingDateEarliest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingDateLatest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'positions' => [
                        [
                            'id' => $ids->create('position'),
                            'orderLineItemId' => $ids->create('line-item'),
                            'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        ],
                    ],
                ],
            ],
            'transactions' => [
                [
                    'id' => $ids->create('transaction'),
                    'paymentMethodId' => $this->getPrePaymentMethodId(),
                    'stateId' => $this->getStateId('open', 'order_transaction.state'),
                    'amount' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                ],
            ],
        ];

        static::getContainer()->get('order.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function getStateId(string $state, string $machine): ?string
    {
        return static::getContainer()->get(Connection::class)
            ->fetchOne(
                '
                SELECT LOWER(HEX(state_machine_state.id))
                FROM state_machine_state
                    INNER JOIN  state_machine
                    ON state_machine.id = state_machine_state.state_machine_id
                    AND state_machine.technical_name = :machine
                WHERE state_machine_state.technical_name = :state
            ',
                [
                    'state' => $state,
                    'machine' => $machine,
                ]
            );
    }

    private function getPrePaymentMethodId(): string
    {
        /** @var EntityRepository<PaymentMethodCollection> $repository */
        $repository = static::getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('handlerIdentifier', PrePayment::class));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];
        static::assertIsString($id);

        return $id;
    }
}
