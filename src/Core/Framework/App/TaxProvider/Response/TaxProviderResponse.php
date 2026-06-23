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
            $response->lineItemTaxes = self::createTaxCollectionMap($data['lineItemTaxes']);
        }

        if (isset($data['deliveryTaxes'])) {
            $response->deliveryTaxes = self::createTaxCollectionMap($data['deliveryTaxes']);
        }

        if (isset($data['cartPriceTaxes'])) {
            $response->cartPriceTaxes = self::createTaxCollection($data['cartPriceTaxes']);
        }

        return $response;
    }

    /**
     * @return array<string, CalculatedTaxCollection>
     */
    private static function createTaxCollectionMap(mixed $taxesByKey): array
    {
        if (!\is_array($taxesByKey)) {
            throw AppException::invalidTaxProviderResponse();
        }

        $taxCollectionMap = [];

        foreach ($taxesByKey as $key => $taxes) {
            $taxCollectionMap[(string) $key] = self::createTaxCollection($taxes);
        }

        return $taxCollectionMap;
    }

    private static function createTaxCollection(mixed $taxes): CalculatedTaxCollection
    {
        if (!\is_array($taxes)) {
            throw AppException::invalidTaxProviderResponse();
        }

        $taxCollection = new CalculatedTaxCollection();

        foreach ($taxes as $tax) {
            $taxCollection->add(self::createTax($tax));
        }

        return $taxCollection;
    }

    private static function createTax(mixed $tax): CalculatedTax
    {
        if (!\is_array($tax)) {
            throw AppException::invalidTaxProviderResponse();
        }

        foreach (['tax', 'taxRate', 'price'] as $key) {
            if (!isset($tax[$key]) || (!\is_float($tax[$key]) && !\is_int($tax[$key]))) {
                throw AppException::invalidTaxProviderResponse();
            }
        }

        $label = $tax['label'] ?? null;

        if ($label !== null && !\is_string($label)) {
            throw AppException::invalidTaxProviderResponse();
        }

        return new CalculatedTax((float) $tax['tax'], (float) $tax['taxRate'], (float) $tax['price'], $label);
    }
}
