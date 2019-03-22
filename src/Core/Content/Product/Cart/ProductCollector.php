<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CollectorInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Cart\Struct\ProductFetchDefinition;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
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
            function (LineItem $lineItem) {
                return $lineItem->getType() === self::LINE_ITEM_TYPE;
            }
        );

        if (\count($lineItems) <= 0) {
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

    public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, CheckoutContext $context): void
    {
        $productDefinitions = $fetchDefinitions->filterInstance(ProductFetchDefinition::class);

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

        $data->set(self::DATA_KEY, $products);
    }

    public function enrich(StructCollection $data, Cart $cart, CheckoutContext $context): void
    {
        if (!$data->has(self::DATA_KEY)) {
            return;
        }

        /** @var ProductCollection $products */
        $products = $data->get(self::DATA_KEY);

        $flat = array_filter(
            $cart->getLineItems()->getFlat(),
            function (LineItem $lineItem) {
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

            $product = $products->get($id);

            if (!$product) {
                throw new ProductNotFoundException($id);
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
                /* @var ProductEntity $product */
                $lineItem->setDeliveryInformation(
                    new DeliveryInformation(
                        (int) $product->getStock(),
                        (float) $product->getWeight(),
                        $product->getDeliveryDate(),
                        $product->getRestockDeliveryDate(),
                        $product->getShippingFree()
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

            $lineItem->replacePayload([
                'tags' => $product->getTagIds(),
                'categories' => $product->getCategoryTree(),
                'datasheet' => $product->getDatasheetIds(),
            ]);
        }
    }

    private function isSatisfied(LineItem $lineItem): bool
    {
        return ($lineItem->getPriceDefinition() || $lineItem->getPrice())
            && $lineItem->getLabel() !== null
            && $lineItem->getCover() !== null
            && $lineItem->getDescription() !== null
            && $lineItem->getDeliveryInformation() !== null
            && $this->isPayloadSatisfied($lineItem);
    }

    private function isPayloadSatisfied(LineItem $lineItem): bool
    {
        return $lineItem->getPayload() !== null
            && $lineItem->hasPayloadValue('tags')
            && $lineItem->hasPayloadValue('categories')
            && $lineItem->hasPayloadValue('datasheet');
    }
}
