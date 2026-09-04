<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderCollection;
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
 * Regression coverage for the reported cross-border B2B scenario: a commercial customer established in
 * the Netherlands, holding a Dutch VAT ID and taking delivery in Belgium, keeps the intra-community
 * exemption — and the invoice, the cancellation invoice and the credit note all agree with the cart.
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

        // The trait points the billing address at the default country; the reported scenario has the
        // buyer established in the Netherlands and taking delivery in Belgium
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

    public function testAShopWithoutASellerCountryKeepsTheOrderTaxedAndTheNoteOff(): void
    {
        // Without the setting the shop cannot tell a domestic supply from an intra-community one, so
        // the exemption is withheld instead of being granted to every member state including its own
        $this->sellFrom(null);

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

        $this->setVatIds(['NL987654321B02']);

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
        static::assertStringNotContainsString('NL987654321B02', $invoice);
    }

    /**
     * Points `core.basicInformation.sellerCountryId` at a country, or clears it with null.
     */
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
     * @param array<string, mixed> $config additional document configuration for the scenario at hand
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
