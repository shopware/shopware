<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Zugferd;

use horstoeko\zugferd\codelists\ZugferdAllowanceCodes;
use horstoeko\zugferd\codelists\ZugferdDutyTaxFeeCategories;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\codelists\ZugferdSchemeIdentifiers;
use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentValidator;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('after-sales')]
class ZugferdDocument
{
    public const CHARGE_AMOUNT = 'chargeAmount';
    public const LINE_TOTAL_AMOUNT = 'lineTotalAmount';
    public const ALLOWANCE_AMOUNT = 'allowanceAmount';

    // To reduce calculation differences with decimal digits
    protected const MULTIPLIER = 100;

    protected float $chargeAmount = 0.0;

    protected float $lineTotalAmount = 0.0;

    protected float $allowanceAmount = 0.0;

    /**
     * @var array{chargeAmount: array<numeric-string, float>, lineTotalAmount: array<numeric-string, float>, allowanceAmount: array<numeric-string, float>}
     */
    private array $verticalTaxCalculation = [
        self::CHARGE_AMOUNT => [],
        self::LINE_TOTAL_AMOUNT => [],
        self::ALLOWANCE_AMOUNT => [],
    ];

    public function __construct(
        protected readonly ZugferdDocumentBuilder $zugferdBuilder,
        protected readonly bool $isGross = false,
    ) {
    }

    public function getContent(OrderEntity $order): string
    {
        $this->withSummation($order);

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
        $tax = $lineItem->getPrice()?->getCalculatedTaxes()?->first();
        $product = $lineItem->getProduct();

        if ($tax === null || ($totalNet = $this->getPrice($tax)) < 0) {
            throw DocumentException::generationError('Price can\'t be negative or null: ' . $lineItem->getLabel());
        }

        $this->addVerticalTaxCalculation(self::LINE_TOTAL_AMOUNT, $tax->getTaxRate(), $tax->getPrice());
        $this->addLineTotalAmount($totalNet);
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

        foreach ($lineItem->getPrice()->getCalculatedTaxes() as $calculatedTax) {
            $actualAmount = $this->getPrice($calculatedTax);
            $this->addVerticalTaxCalculation(self::ALLOWANCE_AMOUNT, $calculatedTax->getTaxRate(), $calculatedTax->getPrice());

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
            foreach ($delivery->getShippingCosts()->getCalculatedTaxes() as $calculatedTax) {
                $actualAmount = $this->getPrice($calculatedTax);
                $this->addVerticalTaxCalculation(self::CHARGE_AMOUNT, $calculatedTax->getTaxRate(), $calculatedTax->getPrice());

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

    protected function withSummation(OrderEntity $order): void
    {
        $lineTotalAmount = $this->lineTotalAmount;
        $chargeAmount = $this->chargeAmount;
        $allowanceAmount = $this->allowanceAmount;

        if ($this->isGross && $order->getTaxCalculationType() === SalesChannelDefinition::CALCULATION_TYPE_VERTICAL) {
            [
                'lineTotalAmount' => $lineTotalAmount,
                'chargeAmount' => $chargeAmount,
                'allowanceAmount' => $allowanceAmount,
            ] = $this->calculateVerticalTaxes();
        }

        $cashRounding = $order->getItemRounding();
        if ($cashRounding) {
            $lineTotalAmount = (new CashRounding())->cashRound($lineTotalAmount, $cashRounding);
            $chargeAmount = (new CashRounding())->cashRound($chargeAmount, $cashRounding);
            $allowanceAmount = (new CashRounding())->cashRound($allowanceAmount, $cashRounding);
        }

        $this->zugferdBuilder->setDocumentSummation(
            $order->getAmountTotal(),
            $order->getAmountTotal(),
            abs($lineTotalAmount),
            abs($chargeAmount),
            abs($allowanceAmount),
            $order->getAmountNet(),
            $order->getAmountTotal() - $order->getAmountNet()
        );
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
        if ($this->isGross) {
            $price -= $tax->getTax();
        }

        return $price;
    }

    protected function addVerticalTaxCalculation(string $type, float $taxRate, float $amount): void
    {
        if (!$this->isGross) {
            return;
        }

        if (!\array_key_exists($type, $this->verticalTaxCalculation)) {
            return;
        }

        $this->verticalTaxCalculation[$type][(string) $taxRate] = ($amount * self::MULTIPLIER) + ($this->verticalTaxCalculation[$type][(string) $taxRate] ?? 0.0);
    }

    protected function getTaxCode(?CalculatedTax $tax): string
    {
        return match ($tax?->getTaxRate() ?? 0.0) {
            0.0 => ZugferdDutyTaxFeeCategories::ZERO_RATED_GOODS,
            default => ZugferdDutyTaxFeeCategories::STANDARD_RATE,
        };
    }

    /**
     * @return array{chargeAmount: float, lineTotalAmount: float, allowanceAmount: float}
     */
    protected function calculateVerticalTaxes(): array
    {
        $calculatedTaxes = [
            self::CHARGE_AMOUNT => 0.0,
            self::LINE_TOTAL_AMOUNT => 0.0,
            self::ALLOWANCE_AMOUNT => 0.0,
        ];

        foreach ($this->verticalTaxCalculation as $type => $taxes) {
            foreach ($taxes as $taxRate => $gross) {
                $calculatedTaxes[$type] += $gross / (1 + (float) $taxRate / 100);
            }

            $calculatedTaxes[$type] /= self::MULTIPLIER;
        }

        return $calculatedTaxes;
    }
}
