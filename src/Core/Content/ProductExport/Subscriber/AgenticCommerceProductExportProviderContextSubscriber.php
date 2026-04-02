<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Subscriber;

use Shopware\Core\Content\ProductExport\Event\ProductExportRenderBodyContextEvent;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Provider\AgenticCommerceProductExportProviderRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
readonly class AgenticCommerceProductExportProviderContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AgenticCommerceProductExportProviderRegistry $providerRegistry,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductExportRenderBodyContextEvent::class => 'extendBodyContext',
        ];
    }

    public function extendBodyContext(ProductExportRenderBodyContextEvent $event): void
    {
        $this->extendContext($event);
    }

    private function extendContext(ProductExportRenderBodyContextEvent $event): void
    {
        $context = $event->getContext();
        $productExport = $context['productExport'] ?? null;
        $salesChannelContext = $context['context'] ?? null;

        if (!$productExport instanceof ProductExportEntity || !$salesChannelContext instanceof SalesChannelContext) {
            return;
        }

        $providerKey = $productExport->getProvider();

        if (!$providerKey) {
            return;
        }

        $provider = $this->providerRegistry->getByTechnicalName($providerKey);

        if ($provider === null) {
            return;
        }

        $event->setContext($provider->extendRenderContext(
            $productExport,
            $salesChannelContext,
            $context,
        ));
    }
}
