<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @package checkout
 *
 * @internal
 */
class CartFacadeHelper implements ResetInterface
{
    private LineItemFactoryRegistry $factory;

    private Processor $processor;

    private Connection $connection;

    private array $currencies = [];

    /**
     * @internal
     */
    public function __construct(LineItemFactoryRegistry $factory, Processor $processor, Connection $connection)
    {
        $this->factory = $factory;
        $this->processor = $processor;
        $this->connection = $connection;
    }

    public function product(string $productId, int $quantity, SalesChannelContext $context): LineItem
    {
        $data = [
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'id' => $productId,
            'referencedId' => $productId,
            'quantity' => $quantity,
        ];

        return $this->factory->create($data, $context);
    }

    public function calculate(Cart $cart, CartBehavior $behavior, SalesChannelContext $context): Cart
    {
        return $this->processor->process($cart, $context, $behavior);
    }

    /**
     * // script value (only use case: shop owner defines a script)
     * set price = services.cart.price.create({
     *      'default': { gross: 100, net: 84.03},
     *      'USD': { gross: 59.5 net: 50 }
     * });
     *      => default will be validate on function call (shop owner has to define it)
     *      => we cannot calculate the net/gross equivalent value because we do not know how the price will be taxed
     *
     * // storage value (custom fields, product.price, etc)
     * set price = {
     *      { gross: 100, net: 50, currencyId: {currency-id} },
     *      { gross: 90, net: 40, currencyId: {currency-id} },
     * }; => default is validate when persisting as storage
     */
    public function price(array $price): PriceCollection
    {
        $collection = new PriceCollection();

        $price = $this->validatePrice($price);

        foreach ($price as $id => $value) {
            $collection->add(
                new Price($id, $value['net'], $value['gross'], $value['linked'] ?? false)
            );
        }

        return $collection;
    }

    public function reset(): void
    {
        $this->currencies = [];
    }

    private function validatePrice(array $price): array
    {
        $price = $this->resolveIsoCodes($price);

        if (!\array_key_exists(Defaults::CURRENCY, $price)) {
            throw CartException::invalidPriceDefinition();
        }

        foreach ($price as $id => $value) {
            if (!Uuid::isValid($id)) {
                throw CartException::invalidPriceDefinition();
            }

            if (!\array_key_exists('gross', $value)) {
                throw CartException::invalidPriceDefinition();
            }

            if (!\array_key_exists('net', $value)) {
                throw CartException::invalidPriceDefinition();
            }
        }

        return $price;
    }

    private function resolveIsoCodes(array $prices): array
    {
        if (empty($this->currencies)) {
            $this->currencies = $this->connection->fetchAllKeyValue('SELECT iso_code, id FROM currency');
        }

        $mapped = [];
        foreach ($prices as $iso => $value) {
            if ($iso === 'default') {
                $mapped[Defaults::CURRENCY] = $value;

                continue;
            }

            if (\array_key_exists('currencyId', $value)) {
                $mapped[$value['currencyId']] = $value;

                continue;
            }

            if (\array_key_exists($iso, $this->currencies)) {
                $mapped[Uuid::fromBytesToHex($this->currencies[$iso])] = $value;
            }
        }

        return $mapped;
    }
}
