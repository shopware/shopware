<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Subscriber;

use Shopware\Core\Content\ProductExport\Event\ProductExportRenderBodyContextEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderFooterContextEvent;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderHeaderContextEvent;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Provider\ProductExportProviderRegistry;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductExportProviderContextSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ProductExportProviderRegistry $providerRegistry)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductExportRenderHeaderContextEvent::class => 'extendHeaderContext',
            ProductExportRenderBodyContextEvent::class => 'extendBodyContext',
            ProductExportRenderFooterContextEvent::class => 'extendFooterContext',
        ];
    }

    public function extendHeaderContext(ProductExportRenderHeaderContextEvent $event): void
    {
        $this->extendContext($event);
    }

    public function extendBodyContext(ProductExportRenderBodyContextEvent $event): void
    {
        $this->extendContext($event);
    }

    public function extendFooterContext(ProductExportRenderFooterContextEvent $event): void
    {
        $this->extendContext($event);
    }

    private function extendContext(
        ProductExportRenderHeaderContextEvent|ProductExportRenderBodyContextEvent|ProductExportRenderFooterContextEvent $event
    ): void
    {
        $context = $event->getContext();
        $productExport = $context['productExport'] ?? null;
        $salesChannelContext = $context['context'] ?? null;

        if (!$productExport instanceof ProductExportEntity || !$salesChannelContext instanceof SalesChannelContext) {
            return;
        }

        $salesChannel = $productExport->getSalesChannel();

        if ($salesChannel === null) {
            return;
        }

        $provider = $this->providerRegistry->getBySalesChannelType($salesChannel->getTypeId());

        if ($provider === null) {
            return;
        }

        $event->setContext($provider->extendRenderContext($productExport, $salesChannelContext, $context));
    }
}
