<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Shopware\Core\Content\Product\AbstractProductVariationBuilder;
use Shopware\Core\Content\Product\AbstractSalesChannelProductBuilder;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSubscriber implements EventSubscriberInterface
{
    private AbstractSalesChannelProductBuilder $salesChannelProductBuilder;

    private AbstractProductVariationBuilder $productVariationBuilder;

    private AbstractProductPriceCalculator $calculator;

    public function __construct(
        AbstractSalesChannelProductBuilder $salesChannelProductBuilder,
        AbstractProductVariationBuilder $productVariationBuilder,
        AbstractProductPriceCalculator $calculator
    ) {
        $this->salesChannelProductBuilder = $salesChannelProductBuilder;
        $this->productVariationBuilder = $productVariationBuilder;
        $this->calculator = $calculator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_LOADED_EVENT => 'loaded',
            'sales_channel.' . ProductEvents::PRODUCT_LOADED_EVENT => 'salesChannelLoaded',
        ];
    }

    public function loaded(EntityLoadedEvent $event): void
    {
        /** @var ProductEntity $product */
        foreach ($event->getEntities() as $product) {
            // CheapestPrice will only be added to SalesChannelProductEntities in the Future
            if (!Feature::isActive('FEATURE_NEXT_16151')) {
                $price = $product->getCheapestPrice();

                if ($price instanceof CheapestPriceContainer) {
                    $resolved = $price->resolve($event->getContext());
                    $product->setCheapestPriceContainer($price);
                    $product->setCheapestPrice($resolved);
                }
            }

            $this->productVariationBuilder->build($product);
        }
    }

    public function salesChannelLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();

        /** @var SalesChannelProductEntity $product */
        foreach ($event->getEntities() as $product) {
            if (Feature::isActive('FEATURE_NEXT_16151')) {
                $price = $product->getCheapestPrice();

                if ($price instanceof CheapestPriceContainer) {
                    $resolved = $price->resolve($event->getContext());
                    $product->setCheapestPriceContainer($price);
                    $product->setCheapestPrice($resolved);
                }
            }

            $this->salesChannelProductBuilder->build($product, $context);
        }

        $this->calculator->calculate(
            $event->getEntities(),
            $event->getSalesChannelContext()
        );
    }
}
