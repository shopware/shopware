<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Cart\CollectorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Cart\Struct\ProductFetchDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Framework\Struct\StructCollection;

class ProductCollector implements CollectorInterface
{
    public const DATA_KEY = 'products';
    public const LINE_ITEM_TYPE = 'product';

    /**
     * @var ProductGatewayInterface
     */
    private $productGateway;

    public function __construct(ProductGatewayInterface $productGateway)
    {
        $this->productGateway = $productGateway;
    }

    public function prepare(StructCollection $definitions, Cart $cart, CheckoutContext $context): void
    {
        $lineItems = array_filter(
            $cart->getLineItems()->getFlat(),
            function(LineItem $lineItem) {
                return $lineItem->getType() === self::LINE_ITEM_TYPE;
            }
        );

        if (count($lineItems) <= 0) {
            return;
        }

        $ids = [];
        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            if ($this->isSatisfied($lineItem)) {
                continue;
            }

            $payload = $lineItem->getPayload();

            $ids[] = $payload['id'];
        }

        $definitions->add(new ProductFetchDefinition($ids));
    }

    public function collect(StructCollection $discountDefinitions, StructCollection $data, Cart $cart, CheckoutContext $context): void
    {
        $productDefinitions = $discountDefinitions->filterInstance(ProductFetchDefinition::class);

        if ($productDefinitions->count() <= 0) {
            return;
        }

        $ids = [];
        /** @var StructCollection $productDefinitions */
        foreach ($productDefinitions as $definition) {
            /** @var ProductFetchDefinition $definition */
            foreach ($definition->getIds() as $id) {
                $ids[] = $id;
            }
        }

        $products = $this->productGateway->get($ids, $context);

        $data->add($products, self::DATA_KEY);
    }

    public function enrich(StructCollection $data, Cart $cart, CheckoutContext $context): void
    {
        if (!$data->has(self::DATA_KEY)) {
            return;
        }

        $products = $data->get(self::DATA_KEY);

        $flat = array_filter(
            $cart->getLineItems()->getFlat(),
            function(LineItem $lineItem) {
                return $lineItem->getType() === self::LINE_ITEM_TYPE;
            }
        );

        if (\count($flat) <= 0) {
            return;
        }

        /** @var LineItem $lineItem */
        foreach ($flat as $lineItem) {
            if ($this->isSatisfied($lineItem)) {
                continue;
            }

            $id = $lineItem->getPayload()['id'];

            /** @var ProductCollection $products */
            $product = $products->get($id);

            /** @var ProductStruct $product */
            if (!$product) {
                throw new \RuntimeException(sprintf('No product data found for line item %s', $lineItem->getKey()));
            }

            if (!$lineItem->getLabel()) {
                $lineItem->setLabel($product->getName());
            }

            if (!$lineItem->getDescription()) {
                $lineItem->setDescription($product->getDescription());
            }

            if (!$lineItem->getCover() && $product->getCover()) {
                $lineItem->setCover($product->getCover()->getMedia());
            }

            if (!$lineItem->getDeliveryInformation()) {
                $lineItem->setDeliveryInformation(
                    new DeliveryInformation(
                        (int) $product->getStock(),
                        (float) $product->getWeight(),
                        $product->getDeliveryDate(),
                        $product->getRestockDeliveryDate()
                    )
                );
            }

            if (!$lineItem->getPriceDefinition() && !$lineItem->getPrice()) {
                $lineItem->setPriceDefinition(
                    $product->getPriceDefinitionForQuantity(
                        $context->getContext(),
                        $lineItem->getQuantity()
                    )
                );
            }
        }
    }

    private function isSatisfied(LineItem $lineItem): bool
    {
        return
            ($lineItem->getPriceDefinition() !== null || $lineItem->getPrice() !== null)
            &&
            $lineItem->getLabel() !== null
            &&
            $lineItem->getCover() !== null
            &&
            $lineItem->getDescription() !== null
            &&
            $lineItem->getDeliveryInformation() !== null
        ;
    }
}
