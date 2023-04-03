<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow;

use Doctrine\DBAL\Connection;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\RendererResult;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Flow\Dispatching\Action\GenerateDocumentAction;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('business-ops')]
class GenerateDocumentActionTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use AdminApiTestBehaviour;
    use ImportTranslationsTrait;

    private Connection $connection;

    private EntityRepository $orderRepository;

    private DocumentGenerator $documentGenerator;

    private Logger $logger;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->documentGenerator = $this->getContainer()->get(DocumentGenerator::class);
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->logger = $this->getContainer()->get('logger');
    }

    /**
     * @dataProvider genDocumentProvider
     */
    public function testGenerateDocument(string $documentType, string $documentRangerType, bool $autoGenInvoiceDoc = false, bool $multipleDoc = false): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer();
        $order = $this->createOrder($customerId, $context);

        $event = new OrderStateMachineStateChangeEvent('state_enter.order.state.in_progress', $order, $context);
        $subscriber = new GenerateDocumentAction($this->documentGenerator, $this->logger);

        if ($multipleDoc) {
            $config = [
                'documentTypes' => [
                    [
                        'documentType' => $documentType,
                        'documentRangerType' => $documentRangerType,
                        'custom' => ['invoiceNumber' => '1100'],
                    ],
                    [
                        'documentType' => DeliveryNoteRenderer::TYPE,
                        'documentRangerType' => 'document_delivery_note',
                    ],
                ],
            ];
        } else {
            $config = [
                'documentType' => $documentType,
                'documentRangerType' => $documentRangerType,
                'custom' => [
                    'invoiceNumber' => '1100',
                ],
            ];
        }

        static::assertEmpty($this->getDocumentId($order->getId()));

        if ($documentType === CreditNoteRenderer::TYPE) {
            $this->addCreditItemToVersionedOrder($order->getId(), $context);
        }

        if ($autoGenInvoiceDoc === true) {
            $this->createInvoiceDocument($order->getId(), $config, $context, $multipleDoc);
        }

        /** @var FlowFactory $flowFactory */
        $flowFactory = $this->getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);
        $flow->setConfig($config);

        $subscriber->handleFlow($flow);

        $referenceDoctype = $documentType === StornoRenderer::TYPE || $documentType === CreditNoteRenderer::TYPE;
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
        $customerId = $this->createCustomer();
        $order = $this->createOrder($customerId, $context);

        $event = new OrderStateMachineStateChangeEvent('state_enter.order.state.in_progress', $order, $context);
        $subscriber = new GenerateDocumentAction($this->documentGenerator, $this->logger);

        $config = array_filter([
            'documentType' => $documentType,
            'documentRangerType' => $documentRangerType,
        ]);

        static::assertEmpty($this->getDocumentId($order->getId()));

        if ($documentType === CreditNoteRenderer::TYPE) {
            $this->addCreditItemToVersionedOrder($order->getId(), $context);
        }

        $handler = new TestHandler(Level::Error);

        $this->logger->pushHandler($handler);

        /** @var FlowFactory $flowFactory */
        $flowFactory = $this->getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);
        $flow->setConfig($config);

        $subscriber->handleFlow($flow);

        static::assertNotEmpty($handler->getRecords());
        static::assertNotEmpty($record = $handler->getRecords()[0]);
        static::assertEquals(
            sprintf(
                'Unable to generate document. Can not generate %s document because no invoice document exists. OrderId: %s',
                str_replace('_', ' ', $documentType),
                $order->getId(),
            ),
            $record->message
        );
    }

    public function testGenerateCustomDocument(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer();
        $order = $this->createOrder($customerId, $context);

        $event = new OrderStateMachineStateChangeEvent('state_enter.order.state.in_progress', $order, $context);
        $subscriber = new GenerateDocumentAction($this->documentGenerator, $this->logger);

        $config = [
            'documentType' => 'customDoc',
            'documentRangerType' => 'document_example',
            'custom' => [
                'invoiceNumber' => '1100',
            ],
        ];

        $this->insertCustomDocument();
        $this->insertRange();

        $registry = $this->getContainer()->get(DocumentRendererRegistry::class);
        $customDocGenerator = new CustomDocRenderer();
        $class = new \ReflectionClass($registry);
        $property = $class->getProperty('documentRenderers');
        $property->setAccessible(true);
        $oldValue = $property->getValue($registry);
        $property->setValue(
            $registry,
            [$customDocGenerator]
        );

        $before = $this->getDocumentId($order->getId());
        static::assertEmpty($before);

        /** @var FlowFactory $flowFactory */
        $flowFactory = $this->getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);
        $flow->setConfig($config);

        $subscriber->handleFlow($flow);

        $after = $this->getDocumentId($order->getId());
        static::assertNotEmpty($after);
        $property->setValue(
            $registry,
            $oldValue
        );
    }

    /**
     * @return iterable<string, array<int, string|bool>>
     */
    public static function genDocumentProvider(): iterable
    {
        yield 'Generate invoice' => ['invoice', 'document_invoice'];
        yield 'Generate multiple doc' => ['invoice', 'document_invoice', false, true];
        yield 'Generate storno with invoice existed' => ['storno', 'document_storno', true];
        yield 'Generate delivery' => ['delivery_note', 'document_delivery_note'];
        yield 'Generate credit with invoice existed' => ['credit_note', 'document_credit_note', true];
    }

    /**
     * @return iterable<string, array<int, string>>
     */
    public static function genErrorDocumentProvider(): iterable
    {
        yield 'Generate storno with invoice not exist' => ['storno', 'document_storno'];
        yield 'Generate credit with invoice not exist' => ['credit_note', 'document_credit_note'];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createInvoiceDocument(string $orderId, array $config, Context $context, bool $multipleDoc): void
    {
        if ($multipleDoc) {
            $docConfig = DocumentConfigurationFactory::createConfiguration($config['documentTypes'][0]);
        } else {
            $docConfig = DocumentConfigurationFactory::createConfiguration($config);
        }

        $operation = new DocumentGenerateOperation($orderId, FileTypes::PDF, $docConfig->jsonSerialize());
        $this->documentGenerator->generate(InvoiceRenderer::TYPE, [$orderId => $operation], $context);
    }

    /**
     * @return array<int, mixed>
     */
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
            json_encode($data, \JSON_THROW_ON_ERROR) ?: ''
        );
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertEmpty($response->getContent());

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

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);
        $versionId = $content['versionId'];
        static::assertEquals($orderId, $content['id']);
        static::assertEquals('order', $content['entity']);
        static::assertTrue(Uuid::isValid($versionId));

        return $versionId;
    }

    private function createOrder(string $customerId, Context $context): OrderEntity
    {
        $orderId = Uuid::randomHex();
        $stateId = $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE);
        $billingAddressId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
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
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
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

/**
 * @internal
 */
#[Package('business-ops')]
class CustomDocRenderer extends AbstractDocumentRenderer
{
    final public const TYPE = 'customDoc';

    public function supports(): string
    {
        return self::TYPE;
    }

    public function getDecorated(): AbstractDocumentRenderer
    {
        throw new DecorationPatternException(self::class);
    }

    public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult
    {
        $result = new RendererResult();

        foreach ($operations as $operation) {
            $rendered = new RenderedDocument('<html>test</html>');
            $rendered->setName('custom.pdf');

            $result->addSuccess($operation->getOrderId(), $rendered);
        }

        return $result;
    }
}
