<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SalesChannelProductSubscriber implements EventSubscriberInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var AbstractProductPriceCalculator
     */
    private $calculator;

    public function __construct(
        SystemConfigService $systemConfigService,
        AbstractProductPriceCalculator $calculator
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->calculator = $calculator;
    }

    public static function getSubscribedEvents()
    {
        return [
            'sales_channel.product.loaded' => 'loaded',
        ];
    }

    public function loaded(SalesChannelEntityLoadedEvent $event): void
    {
        $this->calculator->calculate($event->getEntities(), $event->getSalesChannelContext());

        /** @var SalesChannelProductEntity $product */
        foreach ($event->getEntities() as $product) {
            $product->setCalculatedMaxPurchase(
                $this->calculateMaxPurchase($product, $event->getSalesChannelContext()->getSalesChannel()->getId())
            );

            $this->markAsNew($event->getSalesChannelContext(), $product);
        }
    }

    private function calculateMaxPurchase(SalesChannelProductEntity $product, string $salesChannelId): int
    {
        $fallback = $this->systemConfigService->getInt('core.cart.maxQuantity', $salesChannelId);

        $max = $product->getMaxPurchase() ?? $fallback;

        if ($product->getIsCloseout() && $product->getAvailableStock() < $max) {
            $max = (int) $product->getAvailableStock();
        }

        $max = floor($max / ($product->getPurchaseSteps() ?? 1)) * $product->getPurchaseSteps();

        return (int) max($max, 0);
    }

    private function markAsNew(SalesChannelContext $context, SalesChannelProductEntity $product): void
    {
        $markAsNewDayRange = $this->systemConfigService->get('core.listing.markAsNew', $context->getSalesChannel()->getId());

        $now = new \DateTime();

        /* @var SalesChannelProductEntity $product */
        $product->setIsNew(
            $product->getReleaseDate() instanceof \DateTimeInterface
            && $product->getReleaseDate()->diff($now)->days <= $markAsNewDayRange
        );
    }
}
