<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Zugferd;

use horstoeko\zugferd\codelists\ZugferdAllowanceCodes;
use horstoeko\zugferd\codelists\ZugferdDutyTaxFeeCategories;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\codelists\ZugferdSchemeIdentifiers;
use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentValidator;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class ZugferdDocument
{
    protected float $chargeAmount = 0.0;

    protected float $lineTotalAmount = 0.0;

    protected float $allowanceAmount = 0.0;

    public function __construct(
        protected readonly ZugferdDocumentBuilder $zugferdBuilder,
        protected readonly DocumentConfiguration $config
    ) {
    }

    public function getContent(OrderEntity $order): string
    {
        $this->zugferdBuilder->setDocumentSummation(
            $order->getAmountTotal(),
            $order->getAmountTotal(),
            abs($this->lineTotalAmount),
            abs($this->chargeAmount),
            abs($this->allowanceAmount),
            $order->getAmountNet(),
            $order->getAmountTotal() - $order->getAmountNet()
        );

        $validation = (new ZugferdDocumentValidator($this->zugferdBuilder))->validateDocument();
        if ($validation->count()) {
            $errors = [];
            foreach ($validation as $item) {
                $errors[(string) $item->getMessage()][] = $item->getPropertyPath();
            }

            throw DocumentException::electronicInvoiceViolation($validation->count(), $errors);
        }

        return $this->zugferdBuilder->getContent();
    }

    public function withBuyerInformation(OrderCustomerEntity $customer, OrderAddressEntity $billingAddress): self
    {
        $customerName = $customer->getFirstName() . ' ' . $customer->getLastName();
        if ($customer->getCompany()) {
            $customerName .= ' - ' . $customer->getCompany();
        }

        $replace = $billingAddress->getCountry()?->getIso() . '-';
        $countryStateCode = $billingAddress->getCountryState()?->getShortCode() ?? '';
        if (\str_starts_with($countryStateCode, $replace)) {
            $countryStateCode = \substr($countryStateCode, \strlen($replace));
        }

        $this->zugferdBuilder
            ->setDocumentBuyer($customerName, $customer->getCustomerNumber())
            ->setDocumentBuyerCommunication('EM', $customer->getEmail())
            ->setDocumentBuyerAddress(
                $billingAddress->getStreet(),
                $billingAddress->getAdditionalAddressLine1(),
                $billingAddress->getAdditionalAddressLine2(),
                $billingAddress->getZipcode(),
                $billingAddress->getCity(),
                $billingAddress->getCountry()?->getIso(),
                $countryStateCode
            );

        return $this;
    }

    public function withSellerInformation(DocumentConfiguration $documentConfig): self
    {
        $sellerAddress = [
            'lineOne' => $documentConfig->companyStreet,
            'postCode' => $documentConfig->companyZipcode,
            'city' => $documentConfig->companyCity,
            'country' => $documentConfig->companyCountry?->getIso(),
        ];

        $this->zugferdBuilder
            ->addDocumentPaymentTerm(null, (new \DateTime())->modify($documentConfig->paymentDueDate))
            ->setDocumentSeller($documentConfig->companyName ?? '')
            ->addDocumentSellerTaxRegistration('FC', $documentConfig->taxNumber)
            ->addDocumentSellerTaxRegistration('VA', $documentConfig->vatId)
            ->setDocumentSellerAddress(...$sellerAddress)
            ->setDocumentSellerCommunication('EM', $documentConfig->companyEmail ?? null)
            ->setDocumentSellerContact(
                $documentConfig->executiveDirector ?? null,
                null,
                $documentConfig->companyPhone ?? null,
                null,
                $documentConfig->companyEmail ?? null
            );

        return $this;
    }

    public function withProductLineItem(OrderLineItemEntity $lineItem, string $parentPosition): void
    {
        /** @var CalculatedTax $tax */
        $tax = $lineItem->getPrice()?->getCalculatedTaxes()->first();
        $product = $lineItem->getProduct();
        $totalNet = $this->getPrice($tax);

        $this->addLineTotalAmount($totalNet);
        $this->zugferdBuilder
            ->addNewPosition($parentPosition . $lineItem->getPosition())
            ->setDocumentPositionNetPrice(\round($totalNet / $lineItem->getQuantity(), 2), $lineItem->getQuantity(), ZugferdUnitCodes::REC20_PIECE)
            ->setDocumentPositionQuantity($lineItem->getQuantity(), ZugferdUnitCodes::REC20_PIECE)
            ->addDocumentPositionTax($this->getTaxCode($tax), 'VAT', $tax->getTaxRate())
            ->setDocumentPositionLineSummation($totalNet)
            ->setDocumentPositionProductDetails(
                $lineItem->getLabel(),
                '',
                $product?->getProductNumber(),
                globalIDType: ZugferdSchemeIdentifiers::ISO_6523_0088,
                globalID: $product?->getEan(),
                brandName: $product?->getManufacturer()?->getName()
            );
    }

    public function withDiscountItem(OrderLineItemEntity $lineItem): void
    {
        if ($lineItem->getPrice() === null) {
            return;
        }

        $discountValue = (float) ($lineItem->getPayload()['value'] ?? 0);
        $isPercentage = (($lineItem->getPayload()['discountType'] ?? null) === PromotionDiscountEntity::TYPE_PERCENTAGE) &&
            (abs($lineItem->getTotalPrice()) !== (float) ($lineItem->getPayload()['maxValue'] ?? null));

        foreach ($lineItem->getPrice()->getCalculatedTaxes() as $calculatedTax) {
            $actualAmount = $this->getPrice($calculatedTax);

            $this->addAllowanceAmount($actualAmount);
            $this->zugferdBuilder->addDocumentAllowanceCharge(
                ...[
                    'actualAmount' => abs($actualAmount),
                    'isCharge' => $actualAmount >= 0,
                    'taxCategoryCode' => $this->getTaxCode($calculatedTax),
                    'taxTypeCode' => 'VAT',
                    'rateApplicablePercent' => $calculatedTax->getTaxRate(),
                    'calculationPercent' => $isPercentage ? $discountValue : null,
                    'basisAmount' => $isPercentage ? round(abs($actualAmount) * 100 / $discountValue, 2) : null,
                    'reasonCode' => ZugferdAllowanceCodes::DISCOUNT,
                    'reason' => $lineItem->getReferencedId() ?? $lineItem->getLabel(),
                ]
            );
        }
    }

    public function withGeneralOrderData(\DateTime $deliveryDate, string $documentDate, string $documentNumber, string $isoCode): self
    {
        $this->zugferdBuilder
            ->setDocumentInformation($documentNumber, ZugferdInvoiceType::INVOICE, new \DateTime($documentDate), $isoCode)
            ->setDocumentSupplyChainEvent($deliveryDate);

        return $this;
    }

    public function withDelivery(OrderDeliveryCollection $deliveries): self
    {
        foreach ($deliveries as $delivery) {
            foreach ($delivery->getShippingCosts()->getCalculatedTaxes() as $calculatedTax) {
                // TODO: add promotion shipping discounts with isCharge = false?
                $actualAmount = $this->getPrice($calculatedTax);

                $this->addChargeAmount($actualAmount);
                $this->zugferdBuilder->addDocumentAllowanceCharge(
                    $actualAmount,
                    true,
                    $this->getTaxCode($calculatedTax),
                    'VAT',
                    $calculatedTax->getTaxRate(),
                    reasonCode: 'DL',
                    reason: 'Delivery'
                );
            }
        }

        return $this;
    }

    public function withTaxes(CalculatedTaxCollection $taxes): self
    {
        foreach ($taxes as $tax) {
            $this->zugferdBuilder->addDocumentTax($this->getTaxCode($tax), 'VAT', $this->getPrice($tax), $tax->getTax(), $tax->getTaxRate());
        }

        return $this;
    }

    protected function addChargeAmount(float $chargeAmount): void
    {
        $this->chargeAmount += $chargeAmount;
    }

    protected function addLineTotalAmount(float $lineTotalAmount): void
    {
        $this->lineTotalAmount += $lineTotalAmount;
    }

    protected function addAllowanceAmount(float $allowanceAmount): void
    {
        $this->allowanceAmount += $allowanceAmount;
    }

    protected function getPrice(CalculatedTax $tax): float
    {
        $price = $tax->getPrice();
        if ($this->config->isGross ?? false) {
            $price -= $tax->getTax();
        }

        return $price;
    }

    protected function getTaxCode(CalculatedTax $tax): string
    {
        return match ($tax->getTaxRate()) {
            0.0 => ZugferdDutyTaxFeeCategories::ZERO_RATED_GOODS,
            default => ZugferdDutyTaxFeeCategories::STANDARD_RATE,
        };
    }
}
