<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceDefinitionBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CalculatedListingPrice;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
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

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        QuantityPriceCalculator $priceCalculator,
        ProductPriceDefinitionBuilderInterface $priceDefinitionBuilder,
        SystemConfigService $systemConfigService
    ) {
        $this->priceCalculator = $priceCalculator;
        $this->priceDefinitionBuilder = $priceDefinitionBuilder;
        $this->systemConfigService = $systemConfigService;
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
        $markAsNewDayRange = $this->systemConfigService->get('core.listing.markAsNew', $context->getSalesChannel()->getId());

        $now = new \DateTime();

        /* @var SalesChannelProductEntity $product */
        $product->setIsNew(
            $product->getReleaseDate() instanceof \DateTimeInterface
            && $product->getReleaseDate()->diff($now)->days <= $markAsNewDayRange
        );

        $prices = $this->priceDefinitionBuilder->build($product, $context);

        //calculate listing price
        $product->setCalculatedListingPrice(
            new CalculatedListingPrice(
                $this->priceCalculator->calculate($prices->getFrom(), $context),
                $this->priceCalculator->calculate($prices->getTo(), $context)
            )
        );

        $priceCollection = new PriceCollection();
        foreach ($prices->getPrices() as $price) {
            $priceCollection->add($this->priceCalculator->calculate($price, $context));
        }

        //calculate context prices
        $product->setCalculatedPrices($priceCollection);

        //calculate simple price
        $product->setCalculatedPrice(
            $this->priceCalculator->calculate($prices->getPrice(), $context)
        );
    }
}
