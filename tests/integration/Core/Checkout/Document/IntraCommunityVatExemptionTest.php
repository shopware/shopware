<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\IntraCommunityTaxResolver;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\ProviderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderedDocument;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;

/**
 * Regression coverage for a commercial customer established in the Netherlands, holding a Dutch VAT ID
 * and taking delivery in Belgium: the intra-community exemption holds and the invoice, the cancellation
 * invoice and the credit note all agree with the cart.
 *
 * @internal
 */
#[Package('after-sales')]
class IntraCommunityVatExemptionTest extends TestCase
{
    use CountryAddToSalesChannelTestBehaviour;
    use DocumentTrait;

    private const DUTCH_VAT_ID = 'NL123456789B01';

    private const SWISS_VAT_ID = 'CHE116281838';

    private const INTRA_COMMUNITY_NOTE = 'Intra-community delivery (EU)';

    private const REVERSE_CHARGE_NOTE = 'Reverse charge';

    private Context $context;

    private SalesChannelContext $salesChannelContext;

    private string $customerId;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $netherlands = $this->configureMemberState('NL', 'NL\d{9}B\d{2}');
        $belgium = $this->configureMemberState('BE', 'BE\d{10}');
        $this->addCountriesToSalesChannel([$netherlands, $belgium]);

        // The exemption is only granted against a member state the shop does not supply from itself
        $this->sellFrom('DE');

        $belgianShippingAddressId = Uuid::randomHex();

        $this->customerId = $this->createCustomer(
            [
                'defaultShippingAddressId' => $belgianShippingAddressId,
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                'company' => 'Cross Border Trading BV',
            ],
            [
                'id' => $belgianShippingAddressId,
                'countryId' => $belgium,
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Grote Markt 1',
                'zipcode' => '1000',
                'city' => 'Brussel',
            ]
        );

        $this->setVatIds([self::DUTCH_VAT_ID]);

        // The trait points the billing address at the default country, but the buyer is established
        // in the Netherlands
        $this->moveBillingAddressTo($this->customerId, $netherlands);

        $this->salesChannelContext = $this->createSalesChannelContext();
    }

    public function testACrossBorderB2bOrderIsTaxFreeAndEveryDocumentCarriesTheNote(): void
    {
        $cart = $this->generateDemoCartWithTaxes([19]);
        static::assertSame(CartPrice::TAX_STATE_FREE, $cart->getPrice()->getTaxStatus());

        $orderId = $this->persistCart($cart);
        static::assertSame(CartPrice::TAX_STATE_FREE, $this->getOrderTaxStatus($orderId));

        $invoiceId = $this->generateInvoice($orderId);

        static::assertStringContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(InvoiceRenderer::class), $orderId)
        );

        static::assertStringContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(StornoRenderer::class), $orderId, $invoiceId),
            'The cancellation invoice must reach the same verdict as the invoice it cancels.'
        );

        $this->addCreditItemToOrder($orderId);

        static::assertStringContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(CreditNoteRenderer::class), $orderId, $invoiceId),
            'The credit note must reach the same verdict as the invoice it refers to.'
        );
    }

    public function testTheTaxFreeInvoiceShowsAZeroVatRateInsteadOfEmptyCells(): void
    {
        $orderId = $this->persistCart($this->generateDemoCartWithTaxes([19]));

        $invoice = $this->render(static::getContainer()->get(InvoiceRenderer::class), $orderId);

        // The product row and the shipping costs row
        static::assertSame(2, substr_count($invoice, '<td class="align-right">0 %</td>'));
    }

    public function testDisablingCompanyTaxAfterPurchaseKeepsTheNoteOnEveryDocument(): void
    {
        $cart = $this->generateDemoCartWithTaxes([19]);
        static::assertSame(CartPrice::TAX_STATE_FREE, $cart->getPrice()->getTaxStatus());

        $orderId = $this->persistCart($cart);
        $invoiceId = $this->generateInvoice($orderId);
        $this->addCreditItemToOrder($orderId);

        static::getContainer()->get('country.repository')->update([[
            'id' => $this->salesChannelContext->getShippingLocation()->getCountry()->getId(),
            'companyTax' => [
                'enabled' => false,
                'amount' => 0,
                'currencyId' => $this->context->getCurrencyId(),
            ],
        ]], $this->context);
        static::getContainer()->get(VatIdPatternProvider::class)->reset();
        static::getContainer()->get(IntraCommunityTaxResolver::class)->reset();

        static::assertSame(CartPrice::TAX_STATE_FREE, $this->getOrderTaxStatus($orderId));
        static::assertTrue($this->getV2InvoiceNote($orderId));
        static::assertStringContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(InvoiceRenderer::class), $orderId)
        );
        static::assertStringContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(StornoRenderer::class), $orderId, $invoiceId)
        );
        static::assertStringContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(CreditNoteRenderer::class), $orderId, $invoiceId)
        );
    }

    #[DataProvider('taxedOrderStates')]
    public function testEnablingCompanyTaxAfterPurchaseKeepsTheNoteOffEveryTaxedDocument(string $taxState): void
    {
        static::getContainer()->get('customer_group.repository')->update([[
            'id' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'displayGross' => $taxState === CartPrice::TAX_STATE_GROSS,
        ]], $this->context);
        $countryId = $this->salesChannelContext->getShippingLocation()->getCountry()->getId();
        static::getContainer()->get('country.repository')->update([[
            'id' => $countryId,
            'companyTax' => [
                'enabled' => false,
                'amount' => 0,
                'currencyId' => $this->context->getCurrencyId(),
            ],
        ]], $this->context);
        $this->salesChannelContext = $this->createSalesChannelContext();

        $cart = $this->generateDemoCartWithTaxes([19]);
        static::assertSame($taxState, $cart->getPrice()->getTaxStatus());
        static::assertGreaterThan(0.0, $cart->getPrice()->getCalculatedTaxes()->getAmount());

        $orderId = $this->persistCart($cart);
        $invoiceId = $this->generateInvoice($orderId);
        $this->addCreditItemToOrder($orderId);

        static::getContainer()->get('country.repository')->update([[
            'id' => $countryId,
            'companyTax' => [
                'enabled' => true,
                'amount' => 0,
                'currencyId' => $this->context->getCurrencyId(),
            ],
        ]], $this->context);
        static::getContainer()->get(VatIdPatternProvider::class)->reset();
        static::getContainer()->get(IntraCommunityTaxResolver::class)->reset();

        static::assertSame($taxState, $this->getOrderTaxStatus($orderId));
        static::assertFalse($this->getV2InvoiceNote($orderId));
        static::assertStringNotContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(InvoiceRenderer::class), $orderId)
        );
        static::assertStringNotContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(StornoRenderer::class), $orderId, $invoiceId)
        );
        static::assertStringNotContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(CreditNoteRenderer::class), $orderId, $invoiceId)
        );
    }

    #[DataProvider('taxedOrderStates')]
    public function testAnOrderBelowTheExemptionMinimumKeepsTheNoteOffEveryDocument(string $taxState): void
    {
        static::getContainer()->get('customer_group.repository')->update([[
            'id' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'displayGross' => $taxState === CartPrice::TAX_STATE_GROSS,
        ]], $this->context);
        static::getContainer()->get('country.repository')->update([[
            'id' => $this->salesChannelContext->getShippingLocation()->getCountry()->getId(),
            'companyTax' => [
                'enabled' => true,
                'amount' => 1000,
                'currencyId' => $this->context->getCurrencyId(),
            ],
        ]], $this->context);
        $this->salesChannelContext = $this->createSalesChannelContext();

        $cart = $this->generateDemoCartWithTaxes([19]);
        static::assertSame($taxState, $cart->getPrice()->getTaxStatus());
        static::assertGreaterThan(0.0, $cart->getPrice()->getCalculatedTaxes()->getAmount());
        static::assertLessThan(1000.0, $cart->getPrice()->getTotalPrice());

        $orderId = $this->persistCart($cart);
        static::assertSame($taxState, $this->getOrderTaxStatus($orderId));
        static::assertFalse($this->getV2InvoiceNote($orderId));
        $invoiceId = $this->generateInvoice($orderId);

        static::assertStringNotContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(InvoiceRenderer::class), $orderId)
        );
        static::assertStringNotContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(StornoRenderer::class), $orderId, $invoiceId)
        );

        $this->addCreditItemToOrder($orderId);

        static::assertStringNotContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(CreditNoteRenderer::class), $orderId, $invoiceId)
        );
    }

    public static function taxedOrderStates(): iterable
    {
        yield 'net order' => [CartPrice::TAX_STATE_NET];
        yield 'gross order' => [CartPrice::TAX_STATE_GROSS];
    }

    public function testAVatIdOfNoMemberStateKeepsTheOrderTaxedAndTheNoteOff(): void
    {
        $this->setVatIds([self::SWISS_VAT_ID]);

        $this->salesChannelContext = $this->createSalesChannelContext();

        $cart = $this->generateDemoCartWithTaxes([19]);
        static::assertNotSame(CartPrice::TAX_STATE_FREE, $cart->getPrice()->getTaxStatus());

        $orderId = $this->persistCart($cart);
        $this->generateInvoice($orderId);

        static::assertStringNotContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(InvoiceRenderer::class), $orderId)
        );
    }

    public function testAnEmptyVatIdEntryKeepsTheNoteOnATaxFreeOrder(): void
    {
        $this->setVatIds(['', self::DUTCH_VAT_ID]);

        $this->salesChannelContext = $this->createSalesChannelContext();

        $cart = $this->generateDemoCartWithTaxes([19]);
        static::assertSame(CartPrice::TAX_STATE_FREE, $cart->getPrice()->getTaxStatus());

        $orderId = $this->persistCart($cart);

        static::assertStringContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(InvoiceRenderer::class), $orderId)
        );
        static::assertTrue($this->getV2InvoiceNote($orderId));
    }

    public function testAShopWithoutASellerCountrySuppliesFromTheSalesChannelsDefaultCountry(): void
    {
        // The test sales channel's default country is Germany, which stands in for the missing setting
        $this->sellFrom(null);

        $this->salesChannelContext = $this->createSalesChannelContext();

        $cart = $this->generateDemoCartWithTaxes([19]);
        static::assertSame(CartPrice::TAX_STATE_FREE, $cart->getPrice()->getTaxStatus());

        $orderId = $this->persistCart($cart);
        $this->generateInvoice($orderId);

        static::assertStringContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(InvoiceRenderer::class), $orderId)
        );
    }

    public function testAShopSupplyingFromTheBuyersMemberStateKeepsTheOrderTaxedAndTheNoteOff(): void
    {
        // The buyer is identified in the Netherlands, so a Dutch shop supplies domestically, which
        // Article 138 does not exempt
        $this->sellFrom('NL');

        $this->salesChannelContext = $this->createSalesChannelContext();

        $cart = $this->generateDemoCartWithTaxes([19]);
        static::assertNotSame(CartPrice::TAX_STATE_FREE, $cart->getPrice()->getTaxStatus());

        $orderId = $this->persistCart($cart);
        $this->generateInvoice($orderId);

        static::assertStringNotContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(InvoiceRenderer::class), $orderId)
        );
    }

    public function testTheInvoicePrintsTheVatIdTheOrderWasPlacedWith(): void
    {
        $orderId = $this->persistCart($this->generateDemoCartWithTaxes([19]));

        $this->setVatIds(['DE123456789']);

        $invoice = $this->render(
            static::getContainer()->get(InvoiceRenderer::class),
            $orderId,
            config: ['displayCustomerVatId' => true]
        );

        static::assertStringContainsString(self::INTRA_COMMUNITY_NOTE, $invoice);
        static::assertStringContainsString(
            self::DUTCH_VAT_ID,
            $invoice,
            'The invoice must print the VAT ID the exemption was granted on, which is the order\'s own.'
        );
        static::assertStringNotContainsString('DE123456789', $invoice);
        static::assertTrue($this->getV2InvoiceNote($orderId));
    }

    public function testSellerVatIdWithoutPatternValidationKeepsTheNoteOffEveryDocument(): void
    {
        $this->sellFrom('NL');
        static::getContainer()->get('country.repository')->update([[
            'id' => $this->salesChannelContext->getShippingLocation()->getCountry()->getId(),
            'checkVatIdPattern' => false,
        ]], $this->context);
        static::getContainer()->get(VatIdPatternProvider::class)->reset();
        static::getContainer()->get(IntraCommunityTaxResolver::class)->reset();
        $this->salesChannelContext = $this->createSalesChannelContext();

        $cart = $this->generateDemoCartWithTaxes([19]);
        static::assertNotSame(CartPrice::TAX_STATE_FREE, $cart->getPrice()->getTaxStatus());
        $orderId = $this->persistCart($cart);
        $invoiceId = $this->generateInvoice($orderId);

        static::assertStringNotContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(InvoiceRenderer::class), $orderId)
        );
        static::assertStringNotContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(StornoRenderer::class), $orderId, $invoiceId)
        );

        $this->addCreditItemToOrder($orderId);

        static::assertStringNotContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(CreditNoteRenderer::class), $orderId, $invoiceId)
        );

        $this->setVatIds(['BE0123456789']);

        static::assertStringNotContainsString(
            self::INTRA_COMMUNITY_NOTE,
            $this->render(static::getContainer()->get(InvoiceRenderer::class), $orderId),
            'The note must follow the order VAT ID after the customer changes their VAT ID.'
        );
    }

    public function testDigitalProductsOfABusinessInAnotherMemberStateAreReverseCharged(): void
    {
        $cart = $this->generateDemoCartWithTaxes([19], [ProductDefinition::TYPE_DIGITAL]);
        static::assertSame(CartPrice::TAX_STATE_FREE, $cart->getPrice()->getTaxStatus());

        $orderId = $this->persistCart($cart);
        $invoiceId = $this->generateInvoice($orderId);

        $invoice = $this->render(static::getContainer()->get(InvoiceRenderer::class), $orderId);
        static::assertStringContainsString(self::REVERSE_CHARGE_NOTE, $invoice);
        static::assertStringContainsString(self::DUTCH_VAT_ID, $invoice, 'A reverse-charge invoice names the VAT ID of the buyer.');
        static::assertStringNotContainsString(self::INTRA_COMMUNITY_NOTE, $invoice, 'Nothing was delivered.');

        static::assertStringContainsString(
            self::REVERSE_CHARGE_NOTE,
            $this->render(static::getContainer()->get(StornoRenderer::class), $orderId, $invoiceId),
        );

        $this->addCreditItemToOrder($orderId);

        static::assertStringContainsString(
            self::REVERSE_CHARGE_NOTE,
            $this->render(static::getContainer()->get(CreditNoteRenderer::class), $orderId, $invoiceId),
        );

        $v2Invoice = $this->getV2InvoiceRenderData($orderId);
        static::assertTrue($v2Invoice->reverseCharge);
        static::assertFalse($v2Invoice->intraCommunityDelivery);
    }

    #[DataProvider('taxedMixedOrderProvider')]
    public function testATaxedOrderNotesOnlyItsExemptItems(bool $shippingCountryExempts, bool $billingCountryExempts, string $note, string $absentNote): void
    {
        // Physical goods are shipped to BE, digital products are billed to NL
        static::getContainer()->get('country.repository')->update([
            ['id' => $this->getCountryId('BE'), 'companyTax' => ['enabled' => $shippingCountryExempts, 'amount' => 0, 'currencyId' => Defaults::CURRENCY]],
            ['id' => $this->getCountryId('NL'), 'companyTax' => ['enabled' => $billingCountryExempts, 'amount' => 0, 'currencyId' => Defaults::CURRENCY]],
        ], $this->context);
        $this->salesChannelContext = $this->createSalesChannelContext();

        $cart = $this->generateDemoCartWithTaxes([19, 7], [1 => ProductDefinition::TYPE_DIGITAL]);
        static::assertSame(CartPrice::TAX_STATE_GROSS, $cart->getPrice()->getTaxStatus());

        $orderId = $this->persistCart($cart);

        $invoice = $this->render(static::getContainer()->get(InvoiceRenderer::class), $orderId);
        static::assertStringContainsString($note, $invoice);
        static::assertStringNotContainsString($absentNote, $invoice);

        $v2Invoice = $this->getV2InvoiceRenderData($orderId);
        static::assertSame($note === self::INTRA_COMMUNITY_NOTE, $v2Invoice->intraCommunityDelivery);
        static::assertSame($note === self::REVERSE_CHARGE_NOTE, $v2Invoice->reverseCharge);
    }

    /**
     * @return iterable<string, array{bool, bool, string, string}>
     */
    public static function taxedMixedOrderProvider(): iterable
    {
        yield 'exempt digital products next to taxed goods' => [false, true, self::REVERSE_CHARGE_NOTE, self::INTRA_COMMUNITY_NOTE];
        yield 'exempt goods next to taxed digital products' => [true, false, self::INTRA_COMMUNITY_NOTE, self::REVERSE_CHARGE_NOTE];
    }

    private function getCountryId(string $iso): string
    {
        $countryId = static::getContainer()->get('country.repository')
            ->searchIds((new Criteria())->addFilter(new EqualsFilter('iso', $iso)), $this->context)
            ->firstId();
        static::assertIsString($countryId);

        return $countryId;
    }

    private function getV2InvoiceNote(string $orderId): bool
    {
        return $this->getV2InvoiceRenderData($orderId)->intraCommunityDelivery;
    }

    private function getV2InvoiceRenderData(string $orderId): InvoiceRenderData
    {
        $documentConfig = $this->getBaseConfig('invoice');
        static::assertNotNull($documentConfig);
        $config = $documentConfig->getConfig();
        static::assertNotNull($config);
        $this->upsertBaseConfig(array_replace($config, [
            'displayAdditionalNoteDelivery' => true,
            'companyName' => 'Cross Border Trading',
            'companyStreet' => 'Example Street 1',
            'companyZipcode' => '12345',
            'companyCity' => 'Berlin',
            'companyCountryId' => static::getContainer()->get(SystemConfigService::class)->getString('core.basicInformation.sellerCountryId', TestDefaults::SALES_CHANNEL),
        ]), 'invoice');
        static::getContainer()->get(DocumentConfigLoader::class)->reset();

        $provider = static::getContainer()->get(InvoiceDataProvider::class);
        $criteria = new Criteria([$orderId]);
        $provider->enrichOrderCriteria($criteria);
        $order = static::getContainer()->get('order.repository')->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        return $provider->provideRenderingData(new ProviderInput($order, new DocumentGenerationRequest(
            $orderId,
            DocumentType::INVOICE,
            [DocumentFormat::PDF],
            Uuid::randomHex(),
            documentDate: '2026-05-05T12:00:00+00:00',
        )), $this->context);
    }

    private function sellFrom(?string $iso): void
    {
        $countryId = null;

        if ($iso !== null) {
            /** @var EntityRepository<CountryCollection> $countryRepository */
            $countryRepository = static::getContainer()->get('country.repository');

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('iso', $iso));

            $countryId = $countryRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
            static::assertIsString($countryId);
        }

        static::getContainer()->get(SystemConfigService::class)
            ->set('core.basicInformation.sellerCountryId', $countryId);

        static::getContainer()->get(VatIdPatternProvider::class)->reset();
        static::getContainer()->get(IntraCommunityTaxResolver::class)->reset();
    }

    /**
     * @param list<string> $vatIds
     */
    private function setVatIds(array $vatIds): void
    {
        static::getContainer()->get('customer.repository')->update([[
            'id' => $this->customerId,
            'vatIds' => $vatIds,
        ]], $this->context);
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        return static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [SalesChannelContextService::CUSTOMER_ID => $this->customerId]
        );
    }

    /**
     * Turns a country into a member state that checks the VAT ID format and grants companies tax freedom.
     */
    private function configureMemberState(string $iso, string $vatIdPattern): string
    {
        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', $iso));

        $countryId = $countryRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertIsString($countryId);

        $countryRepository->update([[
            'id' => $countryId,
            'active' => true,
            'isEu' => true,
            'checkVatIdPattern' => true,
            'vatIdPattern' => $vatIdPattern,
            'vatIdRequired' => false,
            'companyTax' => [
                'enabled' => true,
                'amount' => 0,
                'currencyId' => Context::createDefaultContext()->getCurrencyId(),
            ],
        ]], Context::createDefaultContext());

        return $countryId;
    }

    private function moveBillingAddressTo(string $customerId, string $countryId): void
    {
        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');

        $customer = $customerRepository->search(new Criteria([$customerId]), $this->context)->getEntities()->first();
        static::assertNotNull($customer);

        static::getContainer()->get('customer_address.repository')->update([[
            'id' => $customer->getDefaultBillingAddressId(),
            'countryId' => $countryId,
        ]], $this->context);
    }

    private function getOrderTaxStatus(string $orderId): string
    {
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');

        $order = $orderRepository->search(new Criteria([$orderId]), $this->context)->getEntities()->first();
        static::assertNotNull($order);

        $taxStatus = $order->getTaxStatus();
        static::assertIsString($taxStatus);

        return $taxStatus;
    }

    /**
     * The cancellation invoice and the credit note both need an invoice to refer to.
     */
    private function generateInvoice(string $orderId): string
    {
        // A fixed number would collide with the media file another run of this test already stored
        $operation = new DocumentGenerateOperation(
            $orderId,
            FileTypes::PDF,
            ['documentNumber' => Uuid::randomHex()]
        );

        $result = static::getContainer()->get(DocumentGenerator::class)
            ->generate(InvoiceRenderer::TYPE, [$orderId => $operation], $this->context);

        static::assertSame(
            [],
            array_map(static fn (\Throwable $error): string => $error->getMessage(), $result->getErrors())
        );

        $document = $result->getSuccess()->first();
        static::assertNotNull($document);

        return $document->getId();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function render(
        AbstractDocumentRenderer $renderer,
        string $orderId,
        ?string $invoiceId = null,
        array $config = []
    ): string {
        $operation = new DocumentGenerateOperation(
            $orderId,
            HtmlRenderer::FILE_EXTENSION,
            ['displayAdditionalNoteDelivery' => true, 'displayLineItems' => true, 'displayPrices' => true] + $config,
            $invoiceId
        );

        $rendered = $renderer->render([$orderId => $operation], $this->context, new DocumentRendererConfig());

        static::assertSame(
            [],
            array_map(static fn (\Throwable $error): string => $error->getMessage(), $rendered->getErrors())
        );

        $document = $rendered->getSuccess()[$orderId] ?? null;
        static::assertInstanceOf(RenderedDocument::class, $document);

        return $document->getContent();
    }

    private function addCreditItemToOrder(string $orderId): void
    {
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');

        $versionId = $orderRepository->createVersion($orderId, $this->context, 'DRAFT');

        $creditLineItem = new LineItem(Uuid::randomHex(), LineItem::CREDIT_LINE_ITEM_TYPE, null, 1);
        $creditLineItem->setLabel('credit');
        $creditLineItem->setPriceDefinition(new AbsolutePriceDefinition(-10));

        static::getContainer()->get(RecalculationService::class)
            ->addCustomLineItem($orderId, $creditLineItem, $this->context->createWithVersionId($versionId));

        static::getContainer()->get(VersionManager::class)
            ->merge($versionId, WriteContext::createFromContext($this->context));
    }
}
