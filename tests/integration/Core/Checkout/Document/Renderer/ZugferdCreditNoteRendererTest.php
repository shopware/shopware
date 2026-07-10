<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\ZugferdCreditNoteRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class ZugferdCreditNoteRendererTest extends TestCase
{
    use DocumentTrait;
    use SnapshotTesting;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    private ZugferdCreditNoteRenderer $renderer;

    private DocumentGenerator $documentGenerator;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();
        $shippingAddressId = Uuid::randomHex();

        $options = [
            'defaultShippingAddressId' => $shippingAddressId,
        ];

        $additionalAddress = [
            'id' => $shippingAddressId,
            'countryId' => $this->getValidCountryId(),
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Maximilian',
            'lastName' => 'Musterfrau',
            'street' => 'Ebbinghoff 10a',
            'zipcode' => '48624',
            'city' => 'Schöppingen',
        ];

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer($options, $additionalAddress),
            ]
        );
        $this->salesChannelContext->setRuleIds([$priceRuleId]);

        $this->renderer = static::getContainer()->get(ZugferdCreditNoteRenderer::class);
        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
        $this->orderRepository = static::getContainer()->get('order.repository');
    }

    public function testDocumentSnapshot(): void
    {
        $cart = $this->generateDemoCartWithTaxes([7]);
        $orderId = $this->persistCart($cart);

        $config = [
            'vatId' => 'DE123456789',
            'bankBic' => 'DEUTDEDBFRA',
            'bankIban' => 'DE89370400440532013000',
            'bankName' => 'Deutsche Bank',
            'taxNumber' => '123/456/7890',
            'taxOffice' => 'Finanzamt Musterstadt',
            'companyUrl' => 'https://www.example.com',
            'companyName' => 'Example Company',
            'companyEmail' => 'mail@example.com',
            'companyPhone' => '+49 123 4567890',
            'paymentDueDate' => '+30 days',
            'executiveDirector' => 'Max Mustermann',
            'placeOfFulfillment' => 'Musterstadt',
            'placeOfJurisdiction' => 'Musterstadt',
            'documentDate' => '2023-11-24T12:00:00+00:00',
        ];

        $invoiceConfig = new DocumentConfiguration();
        $invoiceConfig->setDocumentNumber('1001');

        $invoicOperation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::PDF,
            $invoiceConfig->jsonSerialize()
        );

        $invoice = $this->documentGenerator->generate(
            InvoiceRenderer::TYPE,
            [$orderId => $invoicOperation],
            $this->context
        )->getSuccess()->first();

        static::assertNotNull($invoice);

        $invoiceId = $invoice->getId();

        $this->addCreditItemsToOrderAfterInvoice($orderId, [-100]);

        $operation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::XML,
            $config,
            $invoiceId
        );

        $processedTemplate = $this->renderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig(),
        );

        $renderedDocument = $processedTemplate->getSuccess()[$orderId];
        static::assertInstanceOf(RenderedDocument::class, $renderedDocument);

        $content = $renderedDocument->getContent();
        static::assertIsString($content);

        $this->assertSnapshot('zugferd_credit_note_document_default', [
            [
                'type' => self::TYPE_XML,
                'actual' => $content,
            ],
        ]);
    }

    /**
     * @param array<int, int> $creditPrices
     */
    private function addCreditItemsToOrderAfterInvoice(string $orderId, array $creditPrices): void
    {
        $versionId = $this->orderRepository->createVersion($orderId, $this->context, 'DRAFT');
        $versionContext = $this->context->createWithVersionId($versionId);

        for ($i = 0, $iMax = \count($creditPrices); $i < $iMax; ++$i) {
            $creditLineItemId = Uuid::randomHex();

            $creditLineItem = new LineItem(
                $creditLineItemId,
                LineItem::CREDIT_LINE_ITEM_TYPE,
                null,
                1
            );

            $creditLineItem->setLabel('credit' . $creditPrices[$i]);
            $creditLineItem->setPriceDefinition(new AbsolutePriceDefinition($creditPrices[$i]));

            $this->getContainer()->get(RecalculationService::class)->addCustomLineItem(
                $orderId,
                $creditLineItem,
                $versionContext,
            );
        }

        static::getContainer()
            ->get(VersionManager::class)
            ->merge($versionId, WriteContext::createFromContext($this->context));
    }
}
