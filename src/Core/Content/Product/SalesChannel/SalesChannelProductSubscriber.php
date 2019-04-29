<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceDefinitionBuilderInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SalesChannelProductSubscriber implements EventSubscriberInterface
{
    /**
     * @var QuantityPriceCalculator
     */
    private $priceCalculator;

    /**
     * @var ProductPriceDefinitionBuilderInterface
     */
    private $priceDefinitionBuilder;

    public function __construct(QuantityPriceCalculator $priceCalculator, ProductPriceDefinitionBuilderInterface $priceDefinitionBuilder)
    {
        $this->priceCalculator = $priceCalculator;
        $this->priceDefinitionBuilder = $priceDefinitionBuilder;
    }

    public static function getSubscribedEvents()
    {
        return [
            'sales_channel.product.loaded' => 'loaded',
        ];
    }

    public function loaded(SalesChannelEntityLoadedEvent $event): void
    {
        /** @var SalesChannelProductEntity $product */
        foreach ($event->getEntities() as $product) {
            $this->calculatePrices($event->getSalesChannelContext(), $product);
        }
    }

    private function calculatePrices(SalesChannelContext $context, SalesChannelProductEntity $product): void
    {
        $prices = $this->priceDefinitionBuilder->build($product, $context);

        //calculate listing price
        $product->setCalculatedListingPrice(
            $this->priceCalculator->calculate($prices->getListingPrice(), $context)
        );

        //calculate context prices
        $product->setCalculatedPrices(
            $this->priceCalculator->calculateCollection($prices->getPrices(), $context)
        );

        //calculate simple price
        $product->setCalculatedPrice(
            $this->priceCalculator->calculate($prices->getPrice(), $context)
        );
    }
}
