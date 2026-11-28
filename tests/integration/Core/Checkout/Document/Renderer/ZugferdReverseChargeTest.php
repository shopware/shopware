<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Renderer;

use horstoeko\zugferd\ZugferdDocumentPdfReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Extension\ResolveDispatchCountryExtension;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\ZugferdCancellationInvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdCreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedCancellationInvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedCreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
use Shopware\Tests\Integration\Core\Checkout\Document\IntraCommunitySupplyTrait;

/**
 * The ZUGFeRD XML of the legacy document pipeline states reverse charge with VAT category AE for a tax-free order
 * whose products are all digital.
 *
 * @internal
 */
#[Package('after-sales')]
class ZugferdReverseChargeTest extends TestCase
{
    use IntraCommunitySupplyTrait;
    use SnapshotTesting;

    protected function setUp(): void
    {
        $this->setUpIntraCommunitySupply();
    }

    public function testTheInvoiceGivesTheLineCategoryAE(): void
    {
        $xml = $this->render(ZugferdRenderer::TYPE, $this->placeReverseChargeOrder());

        $line = '//ram:IncludedSupplyChainTradeLineItem//ram:ApplicableTradeTax';
        static::assertSame(['AE'], self::values($xml, $line . '/ram:CategoryCode'));
        static::assertSame(['0.00'], self::values($xml, $line . '/ram:RateApplicablePercent'));
    }

    public function testTheInvoiceHasOneReverseChargeBreakdown(): void
    {
        $this->assertExemptBreakdown($this->render(ZugferdRenderer::TYPE, $this->placeReverseChargeOrder()), '100.00', 'AE', 'VATEX-EU-AE', 'Reverse charge');
    }

    public function testTheInvoiceNamesTheBuyerVatIdNextToTheSellerVatIdAndTaxNumber(): void
    {
        $this->assertVatIds($this->render(ZugferdRenderer::TYPE, $this->placeReverseChargeOrder()), self::FOREIGN_VAT_ID);
    }

    public function testTheInvoiceHasNoDeliverToAddressAndNoIntraCommunitySupply(): void
    {
        $xml = $this->render(ZugferdRenderer::TYPE, $this->placeReverseChargeOrder());

        static::assertSame([], self::values($xml, '//ram:ShipToTradeParty'));
        static::assertNotContains('K', self::values($xml, '//ram:CategoryCode'));
        static::assertStringNotContainsString('VATEX-EU-IC', $xml);
    }

    public function testTheInvoiceKeepsItsAmounts(): void
    {
        $xml = $this->render(ZugferdRenderer::TYPE, $this->placeReverseChargeOrder());

        $this->assertTotals($xml, '100.00');
    }

    public function testTheInvoicePassesTheXsdValidation(): void
    {
        // render() already fails on the e-invoice violation the library's object validation raises
        $this->assertXsdValid($this->render(ZugferdRenderer::TYPE, $this->placeReverseChargeOrder()));
    }

    public function testTheInvoiceOfAGermanOrderStatesTheReasonInEnglish(): void
    {
        $xml = $this->render(ZugferdRenderer::TYPE, $this->placeReverseChargeOrder($this->getDeDeLanguageId()));

        static::assertSame(['Reverse charge'], self::values($xml, self::BREAKDOWN . '/ram:ExemptionReason'));
    }

    public function testTheCancellationInvoiceStatesReverseChargeWithItsAmounts(): void
    {
        $orderId = $this->placeReverseChargeOrder();

        $xml = $this->render(ZugferdCancellationInvoiceRenderer::TYPE, $orderId, $this->createInvoice($orderId));

        $this->assertReverseCharge($xml, '-100.00');
        $this->assertXsdValid($xml);
        $this->assertTotals($xml, '-100.00');
    }

    public function testTheCreditNoteStatesReverseChargeOnTheCreditLine(): void
    {
        $orderId = $this->placeReverseChargeOrder();
        $invoiceId = $this->createInvoice($orderId);
        $this->addCreditItemToOrder($orderId);

        $xml = $this->render(ZugferdCreditNoteRenderer::TYPE, $orderId, $invoiceId);

        static::assertSame(['credit'], self::values($xml, '//ram:IncludedSupplyChainTradeLineItem//ram:Name'));
        $this->assertReverseCharge($xml, '10.00');
        $this->assertXsdValid($xml);
    }

    #[DataProvider('embeddedDocumentTypes')]
    public function testTheEmbeddedDocumentCarriesReverseCharge(string $documentType): void
    {
        $orderId = $this->placeReverseChargeOrder();
        $invoiceId = $documentType === ZugferdEmbeddedRenderer::TYPE ? null : $this->createInvoice($orderId);
        if ($documentType === ZugferdEmbeddedCreditNoteRenderer::TYPE) {
            $this->addCreditItemToOrder($orderId);
        }

        $xml = ZugferdDocumentPdfReader::getXmlFromContent($this->render($documentType, $orderId, $invoiceId, fileType: FileTypes::PDF));

        $this->assertCategory($xml, 'AE', '0.00');
        static::assertSame(['VATEX-EU-AE'], self::values($xml, self::BREAKDOWN . '/ram:ExemptionReasonCode'));
        static::assertSame([self::FOREIGN_VAT_ID], self::values($xml, '//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function embeddedDocumentTypes(): iterable
    {
        yield 'invoice' => [ZugferdEmbeddedRenderer::TYPE];
        yield 'cancellation invoice' => [ZugferdEmbeddedCancellationInvoiceRenderer::TYPE];
        yield 'credit note' => [ZugferdEmbeddedCreditNoteRenderer::TYPE];
    }

    public function testTheIntraCommunityDeliveryLabelDoesNotChangeTheXml(): void
    {
        $xml = $this->render(ZugferdRenderer::TYPE, $this->placeReverseChargeOrder(), config: ['displayAdditionalNoteDelivery' => false]);

        $this->assertReverseCharge($xml, '100.00');
    }

    /**
     * The story's fixture has the label on for every document type, the other tests run with the default off.
     */
    #[DataProvider('documentTypes')]
    public function testTheXmlStatesReverseChargeWithTheIntraCommunityDeliveryLabelOn(string $documentType): void
    {
        foreach (['invoice', 'storno', 'credit_note'] as $configType) {
            $this->changeDocumentConfig($configType, ['displayAdditionalNoteDelivery' => true]);
        }
        $orderId = $this->placeReverseChargeOrder();
        $invoiceId = $documentType === ZugferdRenderer::TYPE ? null : $this->createInvoice($orderId);
        if ($documentType === ZugferdCreditNoteRenderer::TYPE) {
            $this->addCreditItemToOrder($orderId);
        }

        $xml = $this->render($documentType, $orderId, $invoiceId);

        $this->assertReverseCharge($xml, match ($documentType) {
            ZugferdCancellationInvoiceRenderer::TYPE => '-100.00',
            ZugferdCreditNoteRenderer::TYPE => '10.00',
            default => '100.00',
        });
        $this->assertXsdValid($xml);
    }

    public function testADigitalOrderWithADeliveryStatesReverseChargeInsteadOfAnIntraCommunitySupply(): void
    {
        $orderId = $this->placeReverseChargeOrderWithDelivery();

        $xml = $this->render(ZugferdRenderer::TYPE, $orderId);

        $allowance = '//ram:SpecifiedTradeAllowanceCharge[ram:ChargeIndicator/udt:Indicator="false"]/ram:CategoryTradeTax';
        static::assertSame(['AE'], self::values($xml, $allowance . '/ram:CategoryCode'));
        $this->assertReverseCharge($xml, '90.00');
        static::assertStringNotContainsString('VATEX-EU-IC', $xml);
        static::assertSame([$this->getShippingDateLatest($orderId)], self::values($xml, '//ram:ActualDeliverySupplyChainEvent//udt:DateTimeString'));
    }

    public function testAnOrderWithAPhysicalProductKeepsTheIntraCommunitySupply(): void
    {
        $orderId = $this->placeMixedOrder();

        $xml = $this->render(ZugferdRenderer::TYPE, $orderId);

        $this->assertCategory($xml, 'K', '0.00');
        $this->assertExemptBreakdown($xml, '210.00');
        $this->assertVatIds($xml, self::FOREIGN_VAT_ID);
        $this->assertDeliverTo($xml, $orderId);
    }

    /**
     * @param array{billingCountry?: string, shippingCountry?: string, vatIds?: list<string>, accountType?: string} $order
     */
    #[DataProvider('ordersWithoutReverseCharge')]
    public function testAnotherOrderKeepsTheXmlItHasToday(array $order, string $category, string $standardRate = '19.00', ?string $frenchVatIdPattern = null): void
    {
        $xml = $this->render(ZugferdRenderer::TYPE, $this->placeOrderWithoutReverseCharge($order, $frenchVatIdPattern));

        $this->assertNoIntraCommunitySupply($xml, $category, $standardRate);
    }

    public function testAVatIdInSmallLettersIsWrittenInCapitals(): void
    {
        $this->changeFrenchVatIdCheck(false);

        $xml = $this->render(ZugferdRenderer::TYPE, $this->placeOrder(shippingCountry: 'DE', vatIds: ['nl123456789b01'], products: ['ebook'], withPromotion: false));

        $this->assertCategory($xml, 'AE', '0.00');
        static::assertSame([self::FOREIGN_VAT_ID], self::values($xml, '//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID'));
    }

    public function testWithoutTheVatIdSettingTheInvoiceKeepsCategoryZ(): void
    {
        $orderId = $this->placeReverseChargeOrder();

        $this->changeDocumentConfig('invoice', ['vatId' => '']);

        $this->assertNoIntraCommunitySupply($this->render(ZugferdRenderer::TYPE, $orderId), 'Z');
    }

    public function testEachDocumentTypeReadsItsOwnVatIdSetting(): void
    {
        $orderId = $this->placeReverseChargeOrder();
        $invoiceId = $this->createInvoice($orderId);
        $this->addCreditItemToOrder($orderId);

        $this->changeDocumentConfig('credit_note', ['vatId' => '']);

        $this->assertCategory($this->render(ZugferdRenderer::TYPE, $orderId), 'AE', '0.00');
        $this->assertNoIntraCommunitySupply($this->render(ZugferdCreditNoteRenderer::TYPE, $orderId, $invoiceId), 'Z');
    }

    public function testTheInvoiceReadsTheBillingAddressAndTheVatIdSavedOnTheOrder(): void
    {
        $orderId = $this->placeReverseChargeOrder();

        static::getContainer()->get('customer.repository')->update([[
            'id' => $this->customerId,
            'vatIds' => [self::SELLER_VAT_ID],
            'defaultBillingAddressId' => $this->addressIds['DE'],
        ]], $this->context);

        $this->assertReverseCharge($this->render(ZugferdRenderer::TYPE, $orderId), '100.00');
    }

    public function testADispatchCountryInTheBuyersMemberStateKeepsCategoryZ(): void
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

        $this->assertNoIntraCommunitySupply($this->render(ZugferdRenderer::TYPE, $orderId), 'Z');
    }

    public function testTheTaxFreeOrderWithoutDeliveryOfTheEInvoiceStoryStatesReverseCharge(): void
    {
        $xml = $this->render(ZugferdRenderer::TYPE, $this->placeOrder(products: ['ebook'], withPromotion: false));

        $this->assertReverseCharge($xml, '100.00', self::VAT_ID);
    }

    #[DataProvider('documentTypes')]
    public function testTheXmlOfAReverseChargeOrderMatchesTheSnapshot(string $documentType): void
    {
        $orderId = $this->placeReverseChargeOrder();
        $invoiceId = $this->createInvoice($orderId);
        if ($documentType === ZugferdCreditNoteRenderer::TYPE) {
            $this->addCreditItemToOrder($orderId);
        }

        $this->assertSnapshot($documentType . '_reverse_charge', [
            ['type' => self::TYPE_XML, 'actual' => $this->render($documentType, $orderId, $invoiceId)],
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function documentTypes(): iterable
    {
        yield 'invoice' => [ZugferdRenderer::TYPE];
        yield 'cancellation invoice' => [ZugferdCancellationInvoiceRenderer::TYPE];
        yield 'credit note' => [ZugferdCreditNoteRenderer::TYPE];
    }
}
