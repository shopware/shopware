<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\Event\SalesChannelEntrypointEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class SalesChannelEntrypointService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @return array|string[]
     */
    public function getEntrypointIds(SalesChannelEntity $salesChannel, ?SalesChannelContext $context = null): array
    {
        $entrypointIds = [
            'footer-navigation' => $salesChannel->getFooterCategoryId(),
            'service-navigation' => $salesChannel->getServiceCategoryId(),
            'main-navigation' => $salesChannel->getNavigationCategoryId(),
        ];

        return array_filter(array_merge(
            $entrypointIds,
            $this->getCustomEntrypointIds($salesChannel, $context)->getFlat()
        ));
    }

    /**
     * @return array|string[]
     */
    public function getCustomEntrypoints(SalesChannelEntity $salesChannel, ?SalesChannelContext $context = null): array
    {
        $event = new SalesChannelEntrypointEvent($salesChannel, $context);
        $this->eventDispatcher->dispatch($event);

        return $event->getEntrypoints();
    }

    public function getCustomEntrypointIds(
        SalesChannelEntity $salesChannel,
        ?SalesChannelContext $context
    ): SalesChannelEntrypointCollection {
        $configuredEntrypoints = $salesChannel->getEntrypointIds();
        if (empty($configuredEntrypoints)) {
            return new SalesChannelEntrypointCollection();
        }

        $entrypointIds = [];
        foreach ($this->getCustomEntrypoints($salesChannel, $context) as $customEntrypoint) {
            if (empty($configuredEntrypoints[$customEntrypoint])) {
                continue;
            }

            $entrypointIds[$customEntrypoint] = new SalesChannelEntrypointStruct(
                $customEntrypoint,
                $configuredEntrypoints[$customEntrypoint]
            );
        }

        return new SalesChannelEntrypointCollection($entrypointIds);
    }

    public function getEntrypointId(
        string $entrypoint,
        SalesChannelEntity $salesChannelEntity,
        SalesChannelContext $context
    ): ?SalesChannelEntrypointStruct {
        $entrypoints = $this->getEntrypointIds($salesChannelEntity, $context);

        if (\array_key_exists($entrypoint, $entrypoints)) {
            return new SalesChannelEntrypointStruct($entrypoint, $entrypoints[$entrypoint]);
        }

        return null;
    }
}
