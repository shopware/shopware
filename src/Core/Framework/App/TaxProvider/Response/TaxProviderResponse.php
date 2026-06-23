<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\TaxProvider\Response;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class TaxProviderResponse extends TaxProviderResult
{
    /**
     * @var array<string, CalculatedTaxCollection>|null key is line item id
     */
    protected ?array $lineItemTaxes = null;

    /**
     * @var array<string, CalculatedTaxCollection>|null key is delivery id
     */
    protected ?array $deliveryTaxes = null;

    protected ?CalculatedTaxCollection $cartPriceTaxes = null;

    /**
     * @param array<string, CalculatedTaxCollection>|null $lineItemTaxes
     */
    public function setLineItemTaxes(?array $lineItemTaxes): void
    {
        $this->lineItemTaxes = $lineItemTaxes;
    }

    /**
     * @param array<string, CalculatedTaxCollection>|null $deliveryTaxes
     */
    public function setDeliveryTaxes(?array $deliveryTaxes): void
    {
        $this->deliveryTaxes = $deliveryTaxes;
    }

    public function setCartPriceTaxes(?CalculatedTaxCollection $cartPriceTaxes): void
    {
        $this->cartPriceTaxes = $cartPriceTaxes;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function create(array $data): self
    {
        $response = new self();

        if (isset($data['lineItemTaxes'])) {
            if (!\is_array($data['lineItemTaxes'])) {
                throw AppException::invalidTaxProviderResponse();
            }

            foreach ($data['lineItemTaxes'] as $lineItemId => $taxes) {
                if (!\is_array($taxes)) {
                    throw AppException::invalidTaxProviderResponse();
                }

                $lineItemTax = new CalculatedTaxCollection();

                foreach ($taxes as $tax) {
                    if (!\is_array($tax)) {
                        throw AppException::invalidTaxProviderResponse();
                    }

                    $taxAmount = $tax['tax'] ?? null;
                    $taxRate = $tax['taxRate'] ?? null;
                    $price = $tax['price'] ?? null;
                    $label = $tax['label'] ?? null;

                    if (
                        (!\is_float($taxAmount) && !\is_int($taxAmount))
                        || (!\is_float($taxRate) && !\is_int($taxRate))
                        || (!\is_float($price) && !\is_int($price))
                        || ($label !== null && !\is_string($label))
                    ) {
                        throw AppException::invalidTaxProviderResponse();
                    }

                    $lineItemTax->add(new CalculatedTax((float) $taxAmount, (float) $taxRate, (float) $price, $label));
                }

                $response->lineItemTaxes[(string) $lineItemId] = $lineItemTax;
            }
        }

        if (isset($data['deliveryTaxes'])) {
            if (!\is_array($data['deliveryTaxes'])) {
                throw AppException::invalidTaxProviderResponse();
            }

            foreach ($data['deliveryTaxes'] as $deliveryId => $taxes) {
                if (!\is_array($taxes)) {
                    throw AppException::invalidTaxProviderResponse();
                }

                $deliveryTax = new CalculatedTaxCollection();

                foreach ($taxes as $tax) {
                    if (!\is_array($tax)) {
                        throw AppException::invalidTaxProviderResponse();
                    }

                    $taxAmount = $tax['tax'] ?? null;
                    $taxRate = $tax['taxRate'] ?? null;
                    $price = $tax['price'] ?? null;
                    $label = $tax['label'] ?? null;

                    if (
                        (!\is_float($taxAmount) && !\is_int($taxAmount))
                        || (!\is_float($taxRate) && !\is_int($taxRate))
                        || (!\is_float($price) && !\is_int($price))
                        || ($label !== null && !\is_string($label))
                    ) {
                        throw AppException::invalidTaxProviderResponse();
                    }

                    $deliveryTax->add(new CalculatedTax((float) $taxAmount, (float) $taxRate, (float) $price, $label));
                }

                $response->deliveryTaxes[(string) $deliveryId] = $deliveryTax;
            }
        }

        if (isset($data['cartPriceTaxes'])) {
            if (!\is_array($data['cartPriceTaxes'])) {
                throw AppException::invalidTaxProviderResponse();
            }

            $cartPriceTaxes = new CalculatedTaxCollection();

            foreach ($data['cartPriceTaxes'] as $tax) {
                if (!\is_array($tax)) {
                    throw AppException::invalidTaxProviderResponse();
                }

                $taxAmount = $tax['tax'] ?? null;
                $taxRate = $tax['taxRate'] ?? null;
                $price = $tax['price'] ?? null;
                $label = $tax['label'] ?? null;

                if (
                    (!\is_float($taxAmount) && !\is_int($taxAmount))
                    || (!\is_float($taxRate) && !\is_int($taxRate))
                    || (!\is_float($price) && !\is_int($price))
                    || ($label !== null && !\is_string($label))
                ) {
                    throw AppException::invalidTaxProviderResponse();
                }

                $cartPriceTaxes->add(new CalculatedTax((float) $taxAmount, (float) $taxRate, (float) $price, $label));
            }

            $response->cartPriceTaxes = $cartPriceTaxes;
        }

        return $response;
    }
}
