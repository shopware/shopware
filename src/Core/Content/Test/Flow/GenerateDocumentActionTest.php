<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentGenerator\CreditNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorInterface;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorRegistry;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\StornoGenerator;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Flow\Dispatching\Action\GenerateDocumentAction;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Content\Flow\Exception\GenerateDocumentActionException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

class GenerateDocumentActionTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use AdminApiTestBehaviour;
    use ImportTranslationsTrait;

    private ?Connection $connection;

    private ?EntityRepositoryInterface $orderRepository;

    private ?DocumentService $documentService;

    private ?NumberRangeValueGeneratorInterface $numberRange;

    private ?LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->documentService = $this->getContainer()->get(DocumentService::class);
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->numberRange = $this->getContainer()->get(NumberRangeValueGeneratorInterface::class);
        $this->logger = $this->getContainer()->get(LoggerInterface::class);
    }

    /**
     * @dataProvider genDocumentProvider
     */
    public function testGenerateDocument(string $documentType, string $documentRangerType, bool $autoGenInvoiceDoc = false, bool $multipleDoc = false): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $order = $this->createOrder($customerId, $context);

        $event = new OrderStateMachineStateChangeEvent('state_enter.order.state.in_progress', $order, $context);
        $subscriber = new GenerateDocumentAction(
            $this->documentService,
            $this->numberRange,
            $this->connection,
            $this->logger
        );

        if ($multipleDoc) {
            $config = array_filter([
                'documentTypes' => [
                    [
                        'documentType' => $documentType,
                        'documentRangerType' => $documentRangerType,
                        'custom' => ['invoiceNumber' => '1100'],
                    ],
                    [
                        'documentType' => DeliveryNoteGenerator::DELIVERY_NOTE,
                        'documentRangerType' => 'document_delivery_note',
                    ],
                ],
            ]);
        } else {
            $config = array_filter([
                'documentType' => $documentType,
                'documentRangerType' => $documentRangerType,
                'custom' => [
                    'invoiceNumber' => '1100',
                ],
            ]);
        }

        static::assertEmpty($this->getDocumentId($order->getId()));

        if ($documentType === CreditNoteGenerator::CREDIT_NOTE) {
            $this->addCreditItemToVersionedOrder($order->getId(), $context);
        }

        if ($autoGenInvoiceDoc === true) {
            $this->createInvoiceDocument($order->getId(), $config, $context, $multipleDoc);
        }

        $subscriber->handle(new FlowEvent(GenerateDocumentAction::getName(), new FlowState($event), $config));

        $referenceDoctype = $documentType === StornoGenerator::STORNO || $documentType === CreditNoteGenerator::CREDIT_NOTE;
        if ($referenceDoctype && !$autoGenInvoiceDoc && empty($multipleDoc)) {
            static::assertEmpty($this->getDocumentId($order->getId()));
        } else {
            static::assertNotEmpty($this->getDocumentId($order->getId()));
        }
    }

    /**
     * @dataProvider genErrorDocumentProvider
     */
    public function testGenerateDocumentError(string $documentType, string $documentRangerType): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $order = $this->createOrder($customerId, $context);

        $event = new OrderStateMachineStateChangeEvent('state_enter.order.state.in_progress', $order, $context);
        $subscriber = new GenerateDocumentAction(
            $this->documentService,
            $this->numberRange,
            $this->connection,
        );

        $config = array_filter([
            'documentType' => $documentType,
            'documentRangerType' => $documentRangerType,
        ]);

        static::assertEmpty($this->getDocumentId($order->getId()));

        if ($documentType === CreditNoteGenerator::CREDIT_NOTE) {
            $this->addCreditItemToVersionedOrder($order->getId(), $context);
        }

        static::expectException(GenerateDocumentActionException::class);
        $subscriber->handle(new FlowEvent(GenerateDocumentAction::getName(), new FlowState($event), $config));
    }

    public function testGenerateCustomDocument(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $order = $this->createOrder($customerId, $context);

        $event = new OrderStateMachineStateChangeEvent('state_enter.order.state.in_progress', $order, $context);
        $subscriber = new GenerateDocumentAction(
            $this->documentService,
            $this->numberRange,
            $this->connection,
        );

        $config = array_filter([
            'documentType' => 'customDoc',
            'documentRangerType' => 'document_example',
            'custom' => [
                'invoiceNumber' => '1100',
            ],
        ]);

        $this->insertCustomDocument();
        $this->insertRange();

        $registry = $this->getContainer()->get(DocumentGeneratorRegistry::class);
        $customDocGenerator = new CustomDoc();
        $class = new \ReflectionClass($registry);
        $property = $class->getProperty('documentGenerators');
        $property->setAccessible(true);
        $oldValue = $property->getValue($registry);
        $property->setValue(
            $registry,
            [$customDocGenerator]
        );

        static::assertEmpty($this->getDocumentId($order->getId()));

        $subscriber->handle(new FlowEvent(GenerateDocumentAction::getName(), new FlowState($event), $config));
        static::assertNotEmpty($this->getDocumentId($order->getId()));
        $property->setValue(
            $registry,
            $oldValue
        );
    }

    public function genDocumentProvider(): iterable
    {
        yield 'Generate invoice' => ['invoice', 'document_invoice'];
        yield 'Generate multiple doc' => ['invoice', 'document_invoice', false, true];
        yield 'Generate storno with invoice existed' => ['storno', 'document_storno', true];
        yield 'Generate delivery' => ['delivery_note', 'document_delivery_note'];
        yield 'Generate credit with invoice existed' => ['credit_note', 'document_credit_note', true];
    }

    public function genErrorDocumentProvider(): iterable
    {
        yield 'Generate storno with invoice not exist' => ['storno', 'document_storno'];
        yield 'Generate credit with invoice not exist' => ['credit_note', 'document_credit_note'];
    }

    private function createInvoiceDocument(string $orderId, array $config, Context $context, bool $multipleDoc): void
    {
        if ($multipleDoc) {
            $docConfig = DocumentConfigurationFactory::createConfiguration($config['documentTypes'][0]);
        } else {
            $docConfig = DocumentConfigurationFactory::createConfiguration($config);
        }

        $this->documentService->create(
            $orderId,
            InvoiceGenerator::INVOICE,
            FileTypes::PDF,
            $docConfig,
            $context
        );
    }

    private function getDocumentId(string $orderId): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT LOWER (HEX(`id`))
                    FROM `document`
                    WHERE hex(`order_id`) = :orderId
                    ORDER BY `document`.`created_at` DESC LIMIT 1',
            [
                'orderId' => $orderId,
            ]
        );
    }

    private function addCreditItemToVersionedOrder(string $orderId, Context $context): void
    {
        $identifier = Uuid::randomHex();
        $creditAmount = -10;
        $data = [
            'identifier' => $identifier,
            'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
            'quantity' => 1,
            'label' => 'awesome credit',
            'description' => 'schubbidu',
            'priceDefinition' => [
                'price' => $creditAmount,
                'quantity' => 1,
                'isCalculated' => false,
                'precision' => 2,
            ],
        ];

        $versionId = $this->createVersionedOrder($orderId);

        // add credit item to order
        $this->getBrowser()->request(
            'POST',
            sprintf(
                '/api/_action/order/%s/creditItem',
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
            json_encode($data)
        );
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $context->createWithVersionId($versionId))->get($orderId);

        static::assertNotEmpty($order);
    }

    private function createVersionedOrder(string $orderId): string
    {
        $this->getBrowser()->request(
            'POST',
            sprintf(
                '/api/_action/version/order/%s',
                $orderId
            )
        );
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $content = json_decode($response->getContent(), true);
        $versionId = $content['versionId'];
        static::assertEquals($orderId, $content['id']);
        static::assertEquals('order', $content['entity']);
        static::assertTrue(Uuid::isValid($versionId));

        return $versionId;
    }

    private function createCustomer(Context $context): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->getContainer()
            ->get('customer.repository')
            ->upsert([$customer], $context);

        return $customerId;
    }

    private function createOrder(string $customerId, Context $context): OrderEntity
    {
        $orderId = Uuid::randomHex();
        $stateId = $this->getContainer()->get(StateMachineRegistry::class)->getInitialState(OrderStates::STATE_MACHINE, $context)->getId();
        $billingAddressId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'orderNumber' => Uuid::randomHex(),
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
            'stateId' => $stateId,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'billingAddressId' => $billingAddressId,
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
                    'id' => Uuid::randomHex(),
                    'identifier' => Uuid::randomHex(),
                    'quantity' => 1,
                    'label' => 'label',
                    'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
                    'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                ],
            ],
            'deliveries' => [
            ],
            'context' => '{}',
            'payload' => '{}',
        ];

        $this->orderRepository->upsert([$order], $context);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context);

        return $order->first();
    }

    private function insertRange(): void
    {
        $numberRangeId = Uuid::randomBytes();
        $numberRangeTypeId = Uuid::randomBytes();

        $this->insertNumberRange($this->connection, $numberRangeId, $numberRangeTypeId);
        $this->insertTranslations($this->connection, $numberRangeId, $numberRangeTypeId);
    }

    private function insertNumberRange(Connection $connection, string $numberRangeId, string $numberRangeTypeId): void
    {
        $connection->insert('number_range_type', [
            'id' => $numberRangeTypeId,
            'global' => 0,
            'technical_name' => 'document_example',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('number_range', [
            'id' => $numberRangeId,
            'type_id' => $numberRangeTypeId,
            'global' => 0,
            'pattern' => '{n}',
            'start' => 10000,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $connection->insert('number_range_sales_channel', [
            'id' => Uuid::randomBytes(),
            'number_range_id' => $numberRangeId,
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'number_range_type_id' => $numberRangeTypeId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function insertTranslations(Connection $connection, string $numberRangeId, string $numberRangeTypeId): void
    {
        $numberRangeTranslations = new Translations(
            [
                'number_range_id' => $numberRangeId,
                'name' => 'Beispiel',
            ],
            [
                'number_range_id' => $numberRangeId,
                'name' => 'Example',
            ]
        );

        $numberRangeTypeTranslations = new Translations(
            [
                'number_range_type_id' => $numberRangeTypeId,
                'type_name' => 'Beispiel',
            ],
            [
                'number_range_type_id' => $numberRangeTypeId,
                'type_name' => 'Example',
            ]
        );

        $this->importTranslation(
            'number_range_translation',
            $numberRangeTranslations,
            $connection
        );

        $this->importTranslation(
            'number_range_type_translation',
            $numberRangeTypeTranslations,
            $connection
        );
    }

    private function insertCustomDocument(): void
    {
        $documentTypeId = Uuid::randomBytes();

        $this->connection->insert('document_type', [
            'id' => $documentTypeId,
            'technical_name' => 'customDoc',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->addTranslations($documentTypeId);
    }

    private function addTranslations(string $documentTypeId): void
    {
        $englishName = 'Example document type name';
        $germanName = 'Beispiel Dokumententyp Name';

        $documentTypeTranslations = new Translations(
            [
                'document_type_id' => $documentTypeId,
                'name' => $germanName,
            ],
            [
                'document_type_id' => $documentTypeId,
                'name' => $englishName,
            ]
        );

        $this->importTranslation(
            'document_type_translation',
            $documentTypeTranslations,
            $this->connection
        );
    }
}
class CustomDoc implements DocumentGeneratorInterface
{
    public const CUSTOM_DOC = 'customDoc';

    public function supports(): string
    {
        return self::CUSTOM_DOC;
    }

    public function generate(OrderEntity $order, DocumentConfiguration $config, Context $context, ?string $templatePath = null): string
    {
        return '';
    }

    public function getFileName(DocumentConfiguration $config): string
    {
        return '';
    }
}
