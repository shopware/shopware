<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Zugferd;

use horstoeko\zugferd\codelists\ZugferdAllowanceCodes;
use horstoeko\zugferd\codelists\ZugferdDutyTaxFeeCategories;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\codelists\ZugferdSchemeIdentifiers;
use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentValidator;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('after-sales')]
class ZugferdDocument
{
    public const CHARGE_AMOUNT = 'chargeAmount';
    public const LINE_TOTAL_AMOUNT = 'lineTotalAmount';
    public const ALLOWANCE_AMOUNT = 'allowanceAmount';

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use mappedPrices instead
     */
    protected float $chargeAmount = 0.0;

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use mappedPrices instead
     */
    protected float $lineTotalAmount = 0.0;

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use mappedPrices instead
     */
    protected float $allowanceAmount = 0.0;

    protected float $paidAmount = 0.0;

    /**
     * @var array{chargeAmount: CalculatedPrice[], lineTotalAmount: CalculatedPrice[], allowanceAmount: CalculatedPrice[]}
     */
    private array $mappedPrices = [
        self::CHARGE_AMOUNT => [],
        self::LINE_TOTAL_AMOUNT => [],
        self::ALLOWANCE_AMOUNT => [],
    ];

    public function __construct(
        protected readonly ZugferdDocumentBuilder $zugferdBuilder,
        protected readonly bool $isGross = false,
    ) {
    }

    /**
     * @deprecated tag:v6.8.0 - added new parameter $calculator
     */
    public function getContent(OrderEntity $order/* , AmountCalculator $calculator */): string
    {
        $calculator = func_get_arg(1);
        if (!$calculator instanceof AmountCalculator) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'New required parameter $calculator missing');

            $calculator = new AmountCalculator(
                new CashRounding(),
                new PercentageTaxRuleBuilder(),
                new TaxCalculator()
            );
        }

        $this->summary($order, $calculator);
        $validation = (new ZugferdDocumentValidator($this->zugferdBuilder))->validateDocument();
        if ($validation->count()) {
            $errors = [];
            foreach ($validation as $error) {
                $errors[$error->getPropertyPath()][] = (string) $error->getMessage();
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

    public function withBuyerReference(string $reference): self
    {
        $this->zugferdBuilder->setDocumentBuyerReference($reference);

        return $this;
    }

    public function withSellerInformation(DocumentConfiguration $documentConfig): self
    {
        $sellerAddress = [
            'lineOne' => $documentConfig->getCompanyStreet(),
            'postCode' => $documentConfig->getCompanyZipcode(),
            'city' => $documentConfig->getCompanyCity(),
            'country' => $documentConfig->getCompanyCountry()?->getIso(),
        ];

        $this->zugferdBuilder
            ->addDocumentPaymentTerm(null, (new \DateTime())->modify($documentConfig->getPaymentDueDate() ?: '+30 days'))
            ->setDocumentSeller($documentConfig->getCompanyName() ?? '')
            ->addDocumentSellerTaxRegistration('FC', $documentConfig->getTaxNumber())
            ->addDocumentSellerTaxRegistration('VA', $documentConfig->getVatId())
            ->setDocumentSellerAddress(...$sellerAddress)
            ->setDocumentSellerCommunication('EM', $documentConfig->getCompanyEmail())
            ->setDocumentSellerContact(
                $documentConfig->getExecutiveDirector(),
                null,
                $documentConfig->getCompanyPhone(),
                null,
                $documentConfig->getCompanyEmail()
            );

        return $this;
    }

    public function withProductLineItem(OrderLineItemEntity $lineItem, string $parentPosition): self
    {
        $price = $lineItem->getPrice();
        $tax = $price?->getCalculatedTaxes()?->first();
        $product = $lineItem->getProduct();

        if ($price === null || $tax === null || ($totalNet = $this->getPrice($tax)) < 0) {
            throw DocumentException::generationError('Price can\'t be negative or null: ' . $lineItem->getLabel());
        }

        $this->addMappedPrice(self::LINE_TOTAL_AMOUNT, $price);
        if (!Feature::isActive('v6.8.0.0')) {
            $this->addLineTotalAmount($totalNet);
        }
        $this->zugferdBuilder
            ->addNewPosition($parentPosition . $lineItem->getPosition())
            ->setDocumentPositionNetPrice(\round($totalNet / $lineItem->getQuantity(), 2), $lineItem->getQuantity(), ZugferdUnitCodes::REC20_PIECE)
            ->setDocumentPositionQuantity($lineItem->getQuantity(), ZugferdUnitCodes::REC20_PIECE)
            ->addDocumentPositionTax($this->getTaxCode($tax), 'VAT', $tax->getTaxRate() ?? 0.0)
            ->setDocumentPositionLineSummation($totalNet)
            ->setDocumentPositionProductDetails(
                $lineItem->getLabel(),
                '',
                $product?->getProductNumber(),
                globalIDType: ZugferdSchemeIdentifiers::ISO_6523_0088,
                globalID: $product?->getEan(),
                brandName: $product?->getManufacturer()?->getName()
            );

        return $this;
    }

    public function withDiscountItem(OrderLineItemEntity $lineItem): self
    {
        if ($lineItem->getPrice() === null) {
            return $this;
        }

        $discountValue = (float) ($lineItem->getPayload()['value'] ?? 0);
        $isPercentage = (($lineItem->getPayload()['discountType'] ?? null) === PromotionDiscountEntity::TYPE_PERCENTAGE)
            && (abs($lineItem->getTotalPrice()) !== (float) ($lineItem->getPayload()['maxValue'] ?? null));

        $isCharge = $lineItem->getPrice()->getUnitPrice() >= 0;
        $type = $isCharge ? self::CHARGE_AMOUNT : self::ALLOWANCE_AMOUNT;
        $this->addMappedPrice($type, $lineItem->getPrice());

        foreach ($lineItem->getPrice()->getCalculatedTaxes() as $calculatedTax) {
            $actualAmount = $this->getPrice($calculatedTax);

            if (!Feature::isActive('v6.8.0.0')) {
                if ($isCharge) {
                    $this->addChargeAmount($actualAmount);
                } else {
                    $this->addAllowanceAmount($actualAmount);
                }
            }
            $this->zugferdBuilder->addDocumentAllowanceCharge(
                ...[
                    'actualAmount' => abs($actualAmount),
                    'isCharge' => $isCharge,
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

        return $this;
    }

    public function withGeneralOrderData(?\DateTime $deliveryDate, string $documentDate, string $documentNumber, string $isoCode): self
    {
        $this->zugferdBuilder
            ->setDocumentInformation($documentNumber, ZugferdInvoiceType::INVOICE, new \DateTime($documentDate), $isoCode)
            ->setDocumentSupplyChainEvent($deliveryDate);

        return $this;
    }

    public function withDelivery(OrderDeliveryCollection $deliveries): self
    {
        foreach ($deliveries as $delivery) {
            $this->addMappedPrice(self::CHARGE_AMOUNT, $delivery->getShippingCosts());

            foreach ($delivery->getShippingCosts()->getCalculatedTaxes() as $calculatedTax) {
                $actualAmount = $this->getPrice($calculatedTax);

                if (!Feature::isActive('v6.8.0.0')) {
                    $this->addChargeAmount($actualAmount);
                }
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

    public function withTaxes(CartPrice $price): self
    {
        if ($price->getTaxStatus() === CartPrice::TAX_STATE_FREE) {
            $this->zugferdBuilder->addDocumentTax($this->getTaxCode(null), 'VAT', $price->getTotalPrice(), 0, 0);

            return $this;
        }

        foreach ($price->getCalculatedTaxes() as $tax) {
            $this->zugferdBuilder->addDocumentTax($this->getTaxCode($tax), 'VAT', $this->getPrice($tax), $tax->getTax(), $tax->getTaxRate());
        }

        return $this;
    }

    public function withPaidAmount(float $amount): void
    {
        $this->paidAmount = $amount;
    }

    public function getBuilder(): ZugferdDocumentBuilder
    {
        return $this->zugferdBuilder;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use addMappedPrice instead
     */
    protected function addChargeAmount(float $chargeAmount): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Method and parameter will be removed. Use addMappedPrice instead.');

        $this->chargeAmount += $chargeAmount;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use addMappedPrice instead
     */
    protected function addLineTotalAmount(float $lineTotalAmount): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Method and parameter will be removed. Use addMappedPrice instead.');

        $this->lineTotalAmount += $lineTotalAmount;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use addMappedPrice instead
     */
    protected function addAllowanceAmount(float $allowanceAmount): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Method and parameter will be removed. Use addMappedPrice instead.');

        $this->allowanceAmount += $allowanceAmount;
    }

    protected function getPrice(CalculatedTax $tax): float
    {
        $price = $tax->getPrice();
        if ($this->isGross) {
            $price -= $tax->getTax();
        }

        return $price;
    }

    protected function addMappedPrice(string $type, CalculatedPrice $price): void
    {
        if (!\array_key_exists($type, $this->mappedPrices)) {
            return;
        }

        $this->mappedPrices[$type][] = $price;
    }

    protected function getTaxCode(?CalculatedTax $tax): string
    {
        return match ($tax?->getTaxRate() ?? 0.0) {
            0.0 => ZugferdDutyTaxFeeCategories::ZERO_RATED_GOODS,
            default => ZugferdDutyTaxFeeCategories::STANDARD_RATE,
        };
    }

    private function summary(OrderEntity $order, AmountCalculator $calculator): void
    {
        if ($this->paidAmount > $order->getAmountTotal()) {
            throw DocumentException::generationError('Paid amount is greater than order total amount.');
        }

        $lineTotal = abs($this->calculateTaxes(self::LINE_TOTAL_AMOUNT, $order, $calculator));
        $chargeAmount = abs($this->calculateTaxes(self::CHARGE_AMOUNT, $order, $calculator));
        $allowanceAmount = abs($this->calculateTaxes(self::ALLOWANCE_AMOUNT, $order, $calculator));

        $this->zugferdBuilder
            ->setDocumentSummation(
                $order->getAmountTotal(),
                $order->getAmountTotal() - $this->paidAmount,
                $lineTotal,
                $chargeAmount,
                $allowanceAmount,
                $order->getAmountNet(),
                $order->getAmountTotal() - $order->getAmountNet(),
                $order->getAmountNet() - $lineTotal - $chargeAmount + $allowanceAmount,
                $this->paidAmount
            );
    }

    private function calculateTaxes(string $type, OrderEntity $order, AmountCalculator $calculator): float
    {
        if (!\array_key_exists($type, $this->mappedPrices)) {
            throw DocumentException::generationError(\sprintf('Type "%s" not supported', $type));
        }

        $calculatedTaxes = $calculator->calculateTaxes(
            new PriceCollection($this->mappedPrices[$type]),
            $order->getTaxCalculationType() ?? SalesChannelDefinition::CALCULATION_TYPE_HORIZONTAL,
            $order->getTaxStatus() ?? CartPrice::TAX_STATE_NET,
            $order->getItemRounding() ?? new CashRoundingConfig(2, 0.01, true)
        );

        $netTotal = 0.0;
        foreach ($calculatedTaxes as $tax) {
            $netTotal += $this->getPrice($tax);
        }

        return $netTotal;
    }
}
