<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\TaxProvider\Response;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\TaxProvider\Struct\TaxProviderResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-type TaxResponse = array{ tax: float, taxRate: float, price: float }
 * @phpstan-type CalculatedTaxesCollectionResponse  array<string, TaxResponse[]>
 * @phpstan-type CartPriceTaxesResponse             array<TaxResponse>
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
     * @param array{
     *     lineItemTaxes: CalculatedTaxesCollectionResponse,
     *     deliveryTaxes: CalculatedTaxesCollectionResponse,
     *     cartPriceTaxes: CartPriceTaxesResponse
     * } $data
     */
    public static function create(array $data): self
    {
        $response = new self();

        if (isset($data['lineItemTaxes'])) {
            foreach ($data['lineItemTaxes'] as $lineItemId => $taxes) {
                $lineItemTax = new CalculatedTaxCollection();

                foreach ($taxes as $tax) {
                    $lineItemTax->add(new CalculatedTax($tax['tax'], $tax['taxRate'], $tax['price']));
                }

                $response->lineItemTaxes[$lineItemId] = $lineItemTax;
            }
        }

        if (isset($data['deliveryTaxes'])) {
            foreach ($data['deliveryTaxes'] as $deliveryId => $taxes) {
                $deliveryTax = new CalculatedTaxCollection();

                foreach ($taxes as $tax) {
                    $deliveryTax->add(new CalculatedTax($tax['tax'], $tax['taxRate'], $tax['price']));
                }

                $response->deliveryTaxes[$deliveryId] = $deliveryTax;
            }
        }

        if (isset($data['cartPriceTaxes'])) {
            $cartPriceTaxes = new CalculatedTaxCollection();

            foreach ($data['cartPriceTaxes'] as $tax) {
                $cartPriceTaxes->add(new CalculatedTax($tax['tax'], $tax['taxRate'], $tax['price']));
            }

            $response->cartPriceTaxes = $cartPriceTaxes;
        }

        return $response;
    }
}
