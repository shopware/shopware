<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Extension\ResolveDispatchCountryExtension;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\IntraCommunityTaxResolver;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\OrderDocumentCriteriaFactory;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * Invoices, cancellation invoices and credit notes of a tax-free order whose products are all digital state
 * reverse charge and the customer's VAT ID instead of the intra-community delivery note.
 *
 * @internal
 */
#[Package('after-sales')]
class ReverseChargeNoteTest extends TestCase
{
    use IntraCommunitySupplyTrait;

    private const REVERSE_CHARGE = 'Reverse charge';

    private const INTRA_COMMUNITY_NOTE = 'Intra-community delivery (EU)';

    private const VAT_ID_LINE = 'VAT Reg.No: ' . self::FOREIGN_VAT_ID;

    private const LABEL_ON = ['displayAdditionalNoteDelivery' => true, 'displayCustomerVatId' => false, 'fileTypes' => [HtmlRenderer::FILE_EXTENSION]];

    private const LABEL_OFF = ['displayAdditionalNoteDelivery' => false, 'displayCustomerVatId' => false, 'fileTypes' => [HtmlRenderer::FILE_EXTENSION]];

    protected function setUp(): void
    {
        $this->setUpIntraCommunitySupply();
    }

    public function testTheInvoiceStatesReverseChargeAndTheVatIdInsteadOfTheIntraCommunityNote(): void
    {
        $invoice = $this->renderInvoice($this->placeReverseChargeOrder());

        static::assertSame(1, substr_count($invoice, self::REVERSE_CHARGE));
        static::assertStringContainsString(self::VAT_ID_LINE, $invoice);
        static::assertStringNotContainsString(self::INTRA_COMMUNITY_NOTE, $invoice);
    }

    public function testTheInvoiceOfAGermanOrderStatesReverseChargeInGerman(): void
    {
        $invoice = $this->renderInvoice($this->placeReverseChargeOrder($this->getDeDeLanguageId()));

        static::assertStringContainsString('Steuerschuldnerschaft des Leistungsempfängers', $invoice);
        static::assertStringContainsString('USt-IdNr.: ' . self::FOREIGN_VAT_ID, $invoice);
        static::assertStringNotContainsString('Innergemeinschaftliche Lieferung (EU)', $invoice);
    }

    public function testTheCancellationInvoiceStatesReverseCharge(): void
    {
        $orderId = $this->placeReverseChargeOrder();

        $cancellationInvoice = $this->render(StornoRenderer::TYPE, $orderId, $this->createInvoice($orderId), self::LABEL_ON, HtmlRenderer::FILE_EXTENSION);

        static::assertStringContainsString(self::REVERSE_CHARGE, $cancellationInvoice);
        static::assertStringContainsString(self::VAT_ID_LINE, $cancellationInvoice);
    }

    public function testTheCreditNoteStatesReverseCharge(): void
    {
        $orderId = $this->placeReverseChargeOrder();
        $invoiceId = $this->createInvoice($orderId);
        $this->addCreditItemToOrder($orderId);

        $creditNote = $this->render(CreditNoteRenderer::TYPE, $orderId, $invoiceId, self::LABEL_ON, HtmlRenderer::FILE_EXTENSION);

        static::assertStringContainsString(self::REVERSE_CHARGE, $creditNote);
        static::assertStringContainsString(self::VAT_ID_LINE, $creditNote);
    }

    public function testWithoutTheLabelSettingTheInvoiceStatesNeitherReverseChargeNorTheVatId(): void
    {
        $invoice = $this->render(InvoiceRenderer::TYPE, $this->placeReverseChargeOrder(), config: self::LABEL_OFF, fileType: HtmlRenderer::FILE_EXTENSION);

        static::assertStringNotContainsString(self::REVERSE_CHARGE, $invoice);
        static::assertStringNotContainsString(self::FOREIGN_VAT_ID, $invoice);
    }

    /**
     * @param array{billingCountry?: string, shippingCountry?: string, vatIds?: list<string>, accountType?: string} $order
     */
    #[DataProvider('ordersWithoutNote')]
    public function testAnOrderThatIsNoReverseChargeGetsNoNote(array $order): void
    {
        $invoice = $this->renderInvoice($this->placeOrderWithoutReverseCharge($order));

        static::assertStringNotContainsString(self::REVERSE_CHARGE, $invoice);
        static::assertStringNotContainsString(self::INTRA_COMMUNITY_NOTE, $invoice);
    }

    /**
     * @return iterable<string, array{array{billingCountry?: string, shippingCountry?: string, vatIds?: list<string>}}>
     */
    public static function ordersWithoutNote(): iterable
    {
        yield 'order B, taxed with the rate of the billing country' => [['shippingCountry' => 'CH', 'vatIds' => []]];
        yield 'order C, taxed with the rate of the seller country' => [['shippingCountry' => 'CH', 'vatIds' => [self::SELLER_VAT_ID]]];
        yield 'order D, tax-free to a third country' => [['billingCountry' => 'CH', 'shippingCountry' => 'CH', 'vatIds' => [self::FOREIGN_VAT_ID]]];
    }

    public function testATaxFreePrivateCustomerGetsNoNote(): void
    {
        $this->exemptFrenchPrivateCustomers();

        $orderId = $this->placeOrder(shippingCountry: 'FR', vatIds: [], accountType: CustomerEntity::ACCOUNT_TYPE_PRIVATE, products: ['ebook'], withPromotion: false);

        static::assertSame(CartPrice::TAX_STATE_FREE, $this->getOrderTaxStatus($orderId));
        $invoice = $this->renderInvoice($orderId);
        static::assertStringNotContainsString(self::REVERSE_CHARGE, $invoice);
        static::assertStringNotContainsString(self::INTRA_COMMUNITY_NOTE, $invoice);
    }

    public function testAnOrderWithAPhysicalProductKeepsTheIntraCommunityNote(): void
    {
        $invoice = $this->renderInvoice($this->placeMixedOrder());

        static::assertStringContainsString(self::INTRA_COMMUNITY_NOTE, $invoice);
        static::assertStringNotContainsString(self::REVERSE_CHARGE, $invoice);
    }

    public function testADigitalOrderWithADeliveryStatesReverseCharge(): void
    {
        $invoice = $this->renderInvoice($this->placeReverseChargeOrderWithDelivery());

        static::assertStringContainsString(self::REVERSE_CHARGE, $invoice);
        static::assertStringContainsString(self::VAT_ID_LINE, $invoice);
        static::assertStringNotContainsString(self::INTRA_COMMUNITY_NOTE, $invoice);
    }

    public function testTheInvoiceReadsTheBillingAddressAndTheVatIdSavedOnTheOrder(): void
    {
        $orderId = $this->placeReverseChargeOrder();

        static::getContainer()->get('customer.repository')->update([[
            'id' => $this->customerId,
            'vatIds' => [self::SELLER_VAT_ID],
            'defaultBillingAddressId' => $this->addressIds['DE'],
        ]], $this->context);

        $invoice = $this->renderInvoice($orderId);
        static::assertStringContainsString(self::REVERSE_CHARGE, $invoice);
        static::assertStringContainsString(self::VAT_ID_LINE, $invoice);
    }

    public function testOnlyAnOrderWithAPhysicalProductIsAnIntraCommunityDelivery(): void
    {
        $resolver = static::getContainer()->get(IntraCommunityTaxResolver::class);

        static::assertFalse($resolver->isIntraCommunityDelivery($this->loadOrder($this->placeReverseChargeOrder())));
        static::assertFalse($resolver->isIntraCommunityDelivery($this->loadOrder($this->placeReverseChargeOrderWithDelivery())));
        static::assertTrue($resolver->isIntraCommunityDelivery($this->loadOrder($this->placeMixedOrder())));
    }

    public function testADispatchCountryInTheBuyersMemberStateKeepsTheNoteOff(): void
    {
        $orderId = $this->placeReverseChargeOrder();

        $franceId = $this->getCountryId('FR');
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            ResolveDispatchCountryExtension::onPre(),
            static function (ResolveDispatchCountryExtension $extension) use ($franceId): void {
                $extension->result = $franceId;
                $extension->stopPropagation();
            }
        );

        static::assertStringNotContainsString(self::REVERSE_CHARGE, $this->renderInvoice($orderId));
    }

    public function testTheNewPipelineStatesReverseChargeOnEveryDocument(): void
    {
        foreach (['invoice', 'storno', 'credit_note'] as $documentType) {
            $this->changeDocumentConfig($documentType, self::LABEL_ON);
        }
        $orderId = $this->placeReverseChargeOrder();
        $invoiceId = $this->generateDocument(DocumentType::INVOICE, $orderId, null, DocumentFormat::HTML);

        $documents = [
            'invoice' => $this->getFileContent($invoiceId, DocumentFormat::HTML),
            'cancellation invoice' => $this->generate(DocumentType::CANCELLATION_INVOICE, $orderId, $invoiceId, DocumentFormat::HTML),
        ];
        $this->addCreditItemToOrder($orderId);
        $documents['credit note'] = $this->generate(DocumentType::CREDIT_NOTE, $orderId, $invoiceId, DocumentFormat::HTML);

        foreach ($documents as $name => $document) {
            static::assertStringContainsString(self::REVERSE_CHARGE, $document, $name);
            static::assertStringContainsString(self::VAT_ID_LINE, $document, $name);
        }
    }

    public function testTheNewPipelineKeepsTheIntraCommunityNoteOfAnOrderWithAPhysicalProduct(): void
    {
        $this->changeDocumentConfig('invoice', self::LABEL_ON);

        $invoice = $this->generate(DocumentType::INVOICE, $this->placeMixedOrder(), format: DocumentFormat::HTML);

        static::assertStringContainsString(self::INTRA_COMMUNITY_NOTE, $invoice);
        static::assertStringNotContainsString(self::REVERSE_CHARGE, $invoice);
    }

    private function renderInvoice(string $orderId): string
    {
        return $this->render(InvoiceRenderer::TYPE, $orderId, config: self::LABEL_ON, fileType: HtmlRenderer::FILE_EXTENSION);
    }

    private function loadOrder(string $orderId): OrderEntity
    {
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');

        $order = $orderRepository->search(OrderDocumentCriteriaFactory::create([$orderId]), $this->context)->getEntities()->first();
        static::assertNotNull($order);

        return $order;
    }
}
