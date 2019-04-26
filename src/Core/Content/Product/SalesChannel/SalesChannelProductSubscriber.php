<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
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
        //calculate listing price
        $listingPriceDefinition = $this->priceDefinitionBuilder->buildListingPriceDefinition($product, $context);
        $listingPrice = $this->priceCalculator->calculate($listingPriceDefinition, $context);
        $product->setCalculatedListingPrice($listingPrice);

        //calculate context prices
        $priceRuleDefinitions = $this->priceDefinitionBuilder->buildPriceDefinitions($product, $context);
        $prices = $this->priceCalculator->calculateCollection($priceRuleDefinitions, $context);
        $product->setCalculatedPriceRules($prices);

        //calculate simple price
        $priceDefinition = $this->priceDefinitionBuilder->buildPriceDefinition($product, $context);
        $price = $this->priceCalculator->calculate($priceDefinition, $context);
        $product->setCalculatedPrice($price);
    }
}
