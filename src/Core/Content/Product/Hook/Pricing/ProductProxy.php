<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Hook\Pricing;

use Shopware\Core\Checkout\Cart\Facade\PriceFacade;
use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The `ProductProxy` is a wrapper for the `SalesChannelProductEntity`. It provides access to all properties of the product,
 * but also wraps some data into helper facade classes like `PriceFacade` or `PriceCollectionFacade`.
 *
 * @script-service product
 *
 * @implements \ArrayAccess<string, mixed>
 */
#[Package('inventory')]
class ProductProxy implements \ArrayAccess
{
    public function __construct(
        private readonly Entity $product,
        private readonly SalesChannelContext $context,
        private readonly ScriptPriceStubs $priceStubs
    ) {
    }

    /**
     * The `__get()` function allows access to all properties of the [SalesChannelProductEntity](https://github.com/shopware/platform/blob/trunk/src/Core/Content/Product/SalesChannel/SalesChannelProductEntity.php)
     *
     * @param string $name Name of the property to access
     *
     * @return mixed Returns the value of the property. The value is `mixed` due to the fact that all properties are accessed via `__get()`
     *
     * @example pricing-cases/product-pricing.twig 42 3 Access the product properties
     */
    public function __get(string $name): mixed
    {
        return $this->offsetGet($name);
    }

    /**
     * @internal
     */
    public function __set(string $name, mixed $value): void
    {
        $this->offsetSet($name, $value);
    }

    /**
     * @internal
     */
    public function offsetExists(mixed $property): bool
    {
        return \property_exists($this->product, $property);
    }

    /**
     * @internal
     */
    public function offsetGet(mixed $property): mixed
    {
        return match ($property) {
            'calculatedCheapestPrice' => $this->calculatedCheapestPrice(),
            'calculatedPrice' => $this->calculatedPrice(),
            'calculatedPrices' => $this->calculatedPrices(),
            default => $this->product->$property, /* @phpstan-ignore-line */
        };
    }

    /**
     * The `calculatedCheapestPrice` property returns the cheapest price of the product. The price object will
     * be wrapped into a `PriceFacade` object which allows to manipulate the price.
     *
     * @return PriceFacade|null Returns a `PriceFacade` if the product has a calculated cheapest price, otherwise `null`
     */
    public function calculatedCheapestPrice(): ?PriceFacade
    {
        return $this->product->get('calculatedCheapestPrice') ? new CheapestPriceFacade(
            $this->product,
            $this->product->get('calculatedCheapestPrice'),
            $this->priceStubs,
            $this->context
        ) : null;
    }

    /**
     * The `calculatedPrice` property returns the price of the product. The price object will
     * be wrapped into a `PriceFacade` object which allows to manipulate the price.
     *
     * @return PriceFacade|null Returns a `PriceFacade` if the product has a price, otherwise `null`
     */
    public function calculatedPrice(): ?PriceFacade
    {
        return $this->product->get('calculatedPrice') ? new PriceFacade(
            $this->product,
            $this->product->get('calculatedPrice'),
            $this->priceStubs,
            $this->context
        ) : null;
    }

    /**
     * The `calculatedPrices` property returns the price of the product. The price object will
     * be wrapped into a `PriceCollectionFacade` object which allows to manipulate the collection.
     *
     * @return PriceCollectionFacade|null Returns a `PriceCollectionFacade` if the product has graduated prices, otherwise `null`
     */
    public function calculatedPrices(): ?PriceCollectionFacade
    {
        return $this->product->get('calculatedPrices') ? new PriceCollectionFacade(
            $this->product,
            $this->product->get('calculatedPrices'),
            $this->priceStubs,
            $this->context
        ) : null;
    }

    /**
     * @internal
     */
    public function offsetSet(mixed $property, mixed $value): void
    {
        throw ProductException::proxyManipulationNotAllowed($property);
    }

    /**
     * @internal
     */
    public function offsetUnset(mixed $property): void
    {
        throw ProductException::proxyManipulationNotAllowed($property);
    }
}
