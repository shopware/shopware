<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\DocumentV2\Template\View\AllowanceChargeView;
use Shopware\Core\Checkout\DocumentV2\Template\View\LineItemView;
use Shopware\Core\Checkout\DocumentV2\Template\View\MonetarySummationView;
use Shopware\Core\Checkout\DocumentV2\Template\View\PaymentMeansView;
use Shopware\Core\Checkout\DocumentV2\Template\View\TaxBreakdownView;
use Shopware\Core\Checkout\DocumentV2\Template\View\TradePartyView;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class InvoiceDataProvider extends AbstractDocumentDataProvider
{
    final public const KEY = 'invoice';

    final public const TEMPLATE_PATHS = [
        DocumentFormat::HTML->value => '@Framework/documents/invoice.html.twig',
        DocumentFormat::ZUGFERD_XML->value => '@Framework/documents/zugferd/invoice.xml.twig',
    ];

    public function __construct(
        private DocumentConfigLoader $documentConfigLoader,
        private ValidatorInterface $validator,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getDocumentTypes(): array
    {
        return [
            DocumentType::INVOICE->value,
        ];
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $criteria->addAssociations([
            'currency',
            'language.locale',
            'addresses.country',
            'addresses.salutation',
            'addresses.countryState',
            'orderCustomer.customer',
            'lineItems.product',
            'lineItems.children.product',
            'deliveries.shippingMethod',
            'deliveries.shippingOrderAddress.country',
            'primaryOrderTransaction.paymentMethod',
            'primaryOrderDelivery.shippingOrderAddress.country',
        ]);

        $criteria->getAssociation('lineItems')->addSorting(new FieldSorting('position'));
        $criteria->getAssociation('lineItems.children')->addSorting(new FieldSorting('position'));
        $criteria->getAssociation('deliveries')->addSorting(new FieldSorting('createdAt'));

        /** @deprecated tag:v6.8.0 - Remove when document templates use primaryOrderTransaction instead. */
        $criteria->getAssociation('transactions')
            ->addAssociations(['paymentMethod'])
            ->addSorting(new FieldSorting('createdAt'));
    }

    public function provideRenderingData(
        OrderEntity $order,
        DocumentGenerationRequest $generationRequest,
        Context $context,
    ): InvoiceRenderData {
        $bundle = $this->documentConfigLoader->load(
            $generationRequest->documentType,
            $order->getSalesChannelId(),
            $context,
        );

        $displayAdditionalNoteDelivery = (bool) ($bundle->legacyConfig['displayAdditionalNoteDelivery'] ?? false);

        $isIntraCommunityDelivery = $this->isIntraCommunityDelivery(
            $displayAdditionalNoteDelivery,
            $order,
        );

        $documentNumber = $generationRequest->documentNumber;

        if ($documentNumber === null) {
            throw DocumentV2Exception::missingDocumentNumber($generationRequest->documentType);
        }

        $lineItems = LineItemView::listFromOrder($order);
        $allowanceCharges = AllowanceChargeView::listFromOrder($order);

        return new InvoiceRenderData(
            config: $bundle->config,
            company: $bundle->company,
            display: $bundle->display,
            documentDate: $generationRequest->documentDate,
            documentNumber: $documentNumber,
            documentComment: $generationRequest->documentComment,
            templatePaths: self::TEMPLATE_PATHS,
            typeCode: TypeCode::INVOICE,
            buyerReference: $order->getOrderNumber() ?? '',
            buyer: TradePartyView::buyerFromOrder($order),
            deliveryDate: $this->resolveDeliveryDate($order),
            lineItems: $lineItems,
            allowanceCharges: $allowanceCharges,
            taxBreakdown: TaxBreakdownView::listFromOrder($order),
            monetarySummation: MonetarySummationView::fromOrder(
                $order,
                $lineItems,
                $allowanceCharges
            ),
            paymentMeans: PaymentMeansView::fromOrder(
                $order,
                $bundle->company->bankIban,
                $bundle->company->bankBic,
            ),
            paymentDueDate: $this->resolvePaymentDueDate(
                $generationRequest->documentDate,
                $bundle->legacyConfig
            ),
            intraCommunityDelivery: $isIntraCommunityDelivery,
            custom: ['invoiceNumber' => $documentNumber],
            legacyConfig: $bundle->legacyConfig,
        );
    }

    /**
     * @param array<string, mixed> $legacyConfig
     */
    private function resolvePaymentDueDate(string $documentDate, array $legacyConfig): ?\DateTimeImmutable
    {
        $modifier = $legacyConfig['paymentDueDate'] ?? null;

        if (!\is_string($modifier) || $modifier === '') {
            return null;
        }

        try {
            $base = new \DateTimeImmutable($documentDate);
        } catch (\Exception) {
            return null;
        }

        return $base->modify($modifier) ?: null;
    }

    private function resolveDeliveryDate(OrderEntity $order): ?\DateTimeImmutable
    {
        $delivery = Feature::isActive('v6.8.0.0')
            ? $order->getPrimaryOrderDelivery()
            : $order->getDeliveries()?->first();

        $shippingDate = $delivery?->getShippingDateLatest();

        if ($shippingDate instanceof \DateTimeImmutable) {
            return $shippingDate;
        }

        if ($shippingDate instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($shippingDate);
        }

        return null;
    }

    private function isIntraCommunityDelivery(bool $displayAdditionalNoteDelivery, OrderEntity $order): bool
    {
        if ($displayAdditionalNoteDelivery === false) {
            return false;
        }

        $customerType = $order->getOrderCustomer()?->getCustomer()?->getAccountType();

        if ($customerType !== CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            return false;
        }

        $orderDelivery = Feature::isActive('v6.8.0.0')
            ? $order->getPrimaryOrderDelivery()
            : $order->getDeliveries()?->first();

        if ($orderDelivery === null) {
            return false;
        }

        $country = $orderDelivery->getShippingOrderAddress()?->getCountry();

        if ($country === null) {
            return false;
        }

        if (!$country->getCompanyTax()->getEnabled() || !$country->getIsEu()) {
            return false;
        }

        if ($country->getCheckVatIdPattern() === false) {
            return true;
        }

        $vatIds = $order->getOrderCustomer()?->getVatIds();

        if (!\is_array($vatIds)) {
            return false;
        }

        return $this->validator->validate(
            $vatIds,
            [
                new NotBlank(),
                new CustomerVatIdentification(
                    countryId: $country->getId(),
                ),
            ],
        )->count() === 0;
    }
}
