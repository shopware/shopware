<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Renderer;

use horstoeko\zugferd\ZugferdDocumentPdfReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
use Shopware\Tests\Integration\Core\Checkout\Document\IntraCommunitySupplyTrait;

/**
 * The ZUGFeRD XML of the DocumentV2 pipeline states reverse charge with VAT category AE for a tax-free order
 * whose products are all digital.
 *
 * @internal
 */
#[Package('after-sales')]
class ZugferdXmlReverseChargeTest extends TestCase
{
    use IntraCommunitySupplyTrait;
    use SnapshotTesting;

    protected function setUp(): void
    {
        $this->setUpIntraCommunitySupply();
    }

    public function testTheInvoiceStatesReverseChargeWithItsAmounts(): void
    {
        $xml = $this->generate(DocumentType::INVOICE, $this->placeReverseChargeOrder());

        $this->assertReverseCharge($xml, '100.00');
        static::assertNotContains('K', self::values($xml, '//ram:CategoryCode'));
        static::assertStringNotContainsString('VATEX-EU-IC', $xml);
        $this->assertTotals($xml, '100.00');
        $this->assertXsdValid($xml);
    }

    public function testTheCancellationInvoiceStatesReverseCharge(): void
    {
        $orderId = $this->placeReverseChargeOrder();
        $invoiceId = $this->generateInvoice($orderId);

        $xml = $this->generate(DocumentType::CANCELLATION_INVOICE, $orderId, $invoiceId);

        $this->assertReverseCharge($xml, '-100.00');
        $this->assertTotals($xml, '-100.00');
        $this->assertXsdValid($xml);
    }

    public function testTheCreditNoteStatesReverseChargeOnTheCreditLine(): void
    {
        $orderId = $this->placeReverseChargeOrder();
        $invoiceId = $this->generateInvoice($orderId);
        $this->addCreditItemToOrder($orderId);

        $xml = $this->generate(DocumentType::CREDIT_NOTE, $orderId, $invoiceId);

        static::assertSame(['credit'], self::values($xml, '//ram:IncludedSupplyChainTradeLineItem//ram:Name'));
        $this->assertReverseCharge($xml, '10.00');
        $this->assertXsdValid($xml);
    }

    #[DataProvider('documentTypes')]
    public function testTheEmbeddedPdfCarriesReverseCharge(DocumentType $documentType): void
    {
        $orderId = $this->placeReverseChargeOrder();
        $invoiceId = $documentType === DocumentType::INVOICE ? null : $this->generateInvoice($orderId);
        if ($documentType === DocumentType::CREDIT_NOTE) {
            $this->addCreditItemToOrder($orderId);
        }

        $xml = ZugferdDocumentPdfReader::getXmlFromContent($this->generate($documentType, $orderId, $invoiceId, DocumentFormat::ZUGFERD_EMBEDDED_PDF));

        $this->assertCategory($xml, 'AE', '0.00');
        static::assertSame(['VATEX-EU-AE'], self::values($xml, self::BREAKDOWN . '/ram:ExemptionReasonCode'));
        static::assertSame([self::FOREIGN_VAT_ID], self::values($xml, '//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID'));
    }

    public function testADigitalOrderWithADeliveryStatesReverseChargeInsteadOfAnIntraCommunitySupply(): void
    {
        $xml = $this->generate(DocumentType::INVOICE, $this->placeReverseChargeOrderWithDelivery());

        $this->assertReverseCharge($xml, '90.00');
        static::assertStringNotContainsString('VATEX-EU-IC', $xml);
    }

    public function testAnOrderWithAPhysicalProductKeepsTheIntraCommunitySupply(): void
    {
        $orderId = $this->placeMixedOrder();

        $xml = $this->generate(DocumentType::INVOICE, $orderId);

        $this->assertCategory($xml, 'K', '0.00');
        $this->assertExemptBreakdown($xml, '210.00');
        $this->assertVatIds($xml, self::FOREIGN_VAT_ID);
        $this->assertDeliverTo($xml, $orderId);
    }

    public function testAVatIdInSmallLettersIsWrittenInCapitals(): void
    {
        $this->changeFrenchVatIdCheck(false);

        $xml = $this->generate(DocumentType::INVOICE, $this->placeOrder(shippingCountry: 'DE', vatIds: ['nl123456789b01'], products: ['ebook'], withPromotion: false));

        $this->assertCategory($xml, 'AE', '0.00');
        static::assertSame([self::FOREIGN_VAT_ID], self::values($xml, '//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID'));
    }

    public function testTheTaxFreeOrderWithoutDeliveryOfTheEInvoiceStoryStatesReverseCharge(): void
    {
        $xml = $this->generate(DocumentType::INVOICE, $this->placeOrder(products: ['ebook'], withPromotion: false));

        $this->assertReverseCharge($xml, '100.00', self::VAT_ID);
    }

    public function testTheIntraCommunityDeliveryLabelDoesNotChangeTheXml(): void
    {
        $orderId = $this->placeReverseChargeOrder();

        $this->changeDocumentConfig('invoice', ['displayAdditionalNoteDelivery' => false]);

        $this->assertReverseCharge($this->generate(DocumentType::INVOICE, $orderId), '100.00');
    }

    /**
     * The story's fixture has the label on for every document type, the other tests run with the default off.
     */
    #[DataProvider('documentTypes')]
    public function testTheXmlStatesReverseChargeWithTheIntraCommunityDeliveryLabelOn(DocumentType $documentType): void
    {
        foreach (['invoice', 'storno', 'credit_note'] as $configType) {
            $this->changeDocumentConfig($configType, ['displayAdditionalNoteDelivery' => true]);
        }
        $orderId = $this->placeReverseChargeOrder();
        $invoiceId = $documentType === DocumentType::INVOICE ? null : $this->generateInvoice($orderId);
        if ($documentType === DocumentType::CREDIT_NOTE) {
            $this->addCreditItemToOrder($orderId);
        }

        $xml = $this->generate($documentType, $orderId, $invoiceId);

        $this->assertReverseCharge($xml, match ($documentType) {
            DocumentType::CANCELLATION_INVOICE => '-100.00',
            DocumentType::CREDIT_NOTE => '10.00',
            default => '100.00',
        });
        $this->assertXsdValid($xml);
    }

    /**
     * @param array{billingCountry?: string, shippingCountry?: string, vatIds?: list<string>, accountType?: string} $order
     */
    #[DataProvider('ordersWithoutReverseCharge')]
    public function testAnotherOrderKeepsTheXmlItHasToday(array $order, string $category, string $standardRate = '19.00', ?string $frenchVatIdPattern = null): void
    {
        $xml = $this->generate(DocumentType::INVOICE, $this->placeOrderWithoutReverseCharge($order, $frenchVatIdPattern));

        $this->assertNoIntraCommunitySupply($xml, $category, $standardRate);
    }

    public function testWithoutTheVatIdSettingTheInvoiceKeepsCategoryZ(): void
    {
        $orderId = $this->placeReverseChargeOrder();

        $this->changeDocumentConfig('invoice', ['vatId' => '']);

        $this->assertNoIntraCommunitySupply($this->generate(DocumentType::INVOICE, $orderId), 'Z');
    }

    #[DataProvider('documentTypes')]
    public function testTheXmlOfAReverseChargeOrderMatchesTheSnapshot(DocumentType $documentType): void
    {
        $orderId = $this->placeReverseChargeOrder();
        $invoiceId = $documentType === DocumentType::INVOICE ? null : $this->generateInvoice($orderId);
        if ($documentType === DocumentType::CREDIT_NOTE) {
            $this->addCreditItemToOrder($orderId);
        }

        $this->assertSnapshot($documentType->value . '_reverse_charge', [
            ['type' => self::TYPE_XML, 'actual' => $this->generate($documentType, $orderId, $invoiceId)],
        ]);
    }

    /**
     * @return iterable<string, array{DocumentType}>
     */
    public static function documentTypes(): iterable
    {
        yield 'invoice' => [DocumentType::INVOICE];
        yield 'cancellation invoice' => [DocumentType::CANCELLATION_INVOICE];
        yield 'credit note' => [DocumentType::CREDIT_NOTE];
    }
}
