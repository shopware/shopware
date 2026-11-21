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
 * The ZUGFeRD XML of the DocumentV2 pipeline states an intra-community delivery with VAT category K.
 *
 * @internal
 */
#[Package('after-sales')]
class ZugferdXmlIntraCommunitySupplyTest extends TestCase
{
    use IntraCommunitySupplyTrait;
    use SnapshotTesting;

    protected function setUp(): void
    {
        $this->setUpIntraCommunitySupply();
    }

    public function testTheInvoiceStatesTheIntraCommunitySupplyWithItsAmounts(): void
    {
        $orderId = $this->placeOrder();

        $xml = $this->generate(DocumentType::INVOICE, $orderId);

        $this->assertIntraCommunitySupply($xml, $orderId, '100.00');
        $this->assertXsdValid($xml);
        $summation = '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/';
        static::assertSame(['100.00'], self::values($xml, $summation . 'ram:LineTotalAmount'));
        static::assertSame(['10.00'], self::values($xml, $summation . 'ram:ChargeTotalAmount'));
        static::assertSame(['10.00'], self::values($xml, $summation . 'ram:AllowanceTotalAmount'));
        static::assertSame(['100.00'], self::values($xml, $summation . 'ram:GrandTotalAmount'));
    }

    public function testTheInvoiceGivesTheLineTheShippingChargeAndTheAllowanceCategoryK(): void
    {
        $xml = $this->generate(DocumentType::INVOICE, $this->placeOrder());

        foreach ([
            '//ram:IncludedSupplyChainTradeLineItem//ram:ApplicableTradeTax',
            '//ram:SpecifiedTradeAllowanceCharge[ram:ChargeIndicator/udt:Indicator="true"]/ram:CategoryTradeTax',
            '//ram:SpecifiedTradeAllowanceCharge[ram:ChargeIndicator/udt:Indicator="false"]/ram:CategoryTradeTax',
        ] as $position) {
            static::assertSame(['K'], self::values($xml, $position . '/ram:CategoryCode'), $position);
            static::assertSame(['0.00'], self::values($xml, $position . '/ram:RateApplicablePercent'), $position);
        }
    }

    public function testTheCancellationInvoiceStatesTheIntraCommunitySupply(): void
    {
        $orderId = $this->placeOrder();
        $invoiceId = $this->generateInvoice($orderId);

        $xml = $this->generate(DocumentType::CANCELLATION_INVOICE, $orderId, $invoiceId);

        $this->assertIntraCommunitySupply($xml, $orderId, '-100.00');
        $this->assertXsdValid($xml);
        static::assertSame(['-100.00'], self::values($xml, '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount'));
    }

    public function testTheCreditNoteStatesTheIntraCommunitySupplyOnTheCreditLine(): void
    {
        $orderId = $this->placeOrder();
        $invoiceId = $this->generateInvoice($orderId);
        $this->addCreditItemToOrder($orderId);

        $xml = $this->generate(DocumentType::CREDIT_NOTE, $orderId, $invoiceId);

        static::assertSame(['credit'], self::values($xml, '//ram:IncludedSupplyChainTradeLineItem//ram:Name'));
        $this->assertIntraCommunitySupply($xml, $orderId, '10.00');
        $this->assertXsdValid($xml);
    }

    public function testTheEmbeddedPdfCarriesTheIntraCommunitySupply(): void
    {
        $pdf = $this->generate(DocumentType::INVOICE, $this->placeOrder(), format: DocumentFormat::ZUGFERD_EMBEDDED_PDF);

        $xml = ZugferdDocumentPdfReader::getXmlFromContent($pdf);
        $this->assertCategory($xml, 'K', '0.00');
        static::assertSame(['VATEX-EU-IC'], self::values($xml, self::BREAKDOWN . '/ram:ExemptionReasonCode'));
        static::assertSame([self::VAT_ID], self::values($xml, '//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID'));
    }

    /**
     * @param array{billingCountry?: string, shippingCountry?: string, vatIds?: list<string>, accountType?: string, withPromotion?: bool} $order
     */
    #[DataProvider('ordersWithoutIntraCommunitySupply')]
    public function testAnotherOrderKeepsTheXmlItHasToday(array $order, string $category, ?string $frenchVatIdPattern = null): void
    {
        if ($frenchVatIdPattern !== null) {
            $this->changeFrenchVatIdCheck(true, $frenchVatIdPattern);
        }

        $this->assertNoIntraCommunitySupply($this->generate(DocumentType::INVOICE, $this->placeOrder(...$order)), $category);
    }

    public function testWithoutTheVatIdSettingTheInvoiceKeepsCategoryZ(): void
    {
        $orderId = $this->placeOrder();

        $this->changeDocumentConfig('invoice', ['vatId' => '']);

        $this->assertNoIntraCommunitySupply($this->generate(DocumentType::INVOICE, $orderId), 'Z');
    }

    #[DataProvider('documentTypes')]
    public function testTheXmlOfAnIntraCommunityDeliveryMatchesTheSnapshot(DocumentType $documentType): void
    {
        $orderId = $this->placeOrder();
        $invoiceId = $documentType === DocumentType::INVOICE ? null : $this->generateInvoice($orderId);
        if ($documentType === DocumentType::CREDIT_NOTE) {
            $this->addCreditItemToOrder($orderId);
        }

        $this->assertSnapshot($documentType->value . '_intra_community_supply', [
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
