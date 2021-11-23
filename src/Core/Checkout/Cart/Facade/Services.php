<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Exception\MissingPriceDefinitionException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Services
{
    private LineItemFactoryRegistry $factory;

    private SalesChannelContext $context;

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

    public function setContext(SalesChannelContext $context): void
    {
        $this->context = $context;
    }

    public function product(string $productId, int $quantity): LineItem
    {
        $data = [
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'id' => $productId,
            'referencedId' => $productId,
            'quantity' => $quantity,
        ];

        return $this->factory->create($data, $this->context);
    }

    public function calculate(Cart $cart, CartBehavior $behavior): Cart
    {
        return $this->processor->process($cart, $this->context, $behavior);
    }

    /**
     * // script value (only use case: shop owner defines a script)
     * set price = services.price.create({
     *      'default': { gross: 100, net: 84.03},
     *      'USD': { gross: 59.5 net: 50 }
     * });
     *      => default will be validate on function call (shop owner has to define it)
     *      => we cannot calculate the net/gross equivalent value because we do not know how the price will be taxed
     *
     * // storage value (custom fields, product.price, etc)
     * set price = {
     *      {currency-id}: { gross: 100, net: 50 },
     *      {currency-id}: { gross: 90, net: 40 },
     * }; => default is validate when persisting as storage
     */
    public function price(array $price): PriceCollection
    {
        $collection = new PriceCollection();

        $price = $this->validatePrice($price);

        foreach ($price as $id => $value) {
            $collection->add(
                new Price($id, $value['net'], $value['gross'], false)
            );
        }

        return $collection;
    }

    private function validatePrice(array $price): array
    {
        if (\array_key_exists('default', $price)) {
            $price = $this->resolveIsoCodes($price);
        }

        if (!\array_key_exists(Defaults::CURRENCY, $price)) {
            throw new MissingPriceDefinitionException('Price contains no definition for default currency id');
        }

        foreach ($price as $id => $value) {
            if (!Uuid::isValid($id)) {
                throw new MissingPriceDefinitionException(sprintf('Defined currency id %s is not valid', $id));
            }

            if (!\array_key_exists('gross', $value)) {
                throw new MissingPriceDefinitionException(sprintf('Price for iso %s does not include a gross price', $id));
            }

            if (!\array_key_exists('net', $value)) {
                throw new MissingPriceDefinitionException(sprintf('Price for iso %s does not include a net price', $id));
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

            if (\array_key_exists($iso, $this->currencies)) {
                $mapped[$this->currencies[$iso]] = $value;
            }
        }

        return $mapped;
    }
}
