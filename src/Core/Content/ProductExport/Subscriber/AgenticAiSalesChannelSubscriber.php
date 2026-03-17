<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Subscriber;

use Shopware\Core\Content\ProductExport\Service\ProductExportProvisioner;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
readonly class AgenticAiSalesChannelSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ProductExportProvisioner $productExportProvisioner
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelEvents::SALES_CHANNEL_WRITTEN => 'provisionProductExport',
        ];
    }

    public function provisionProductExport(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $writeResult) {
            $typeId = $writeResult->getPayload()['typeId'] ?? null;

            if ($typeId !== Defaults::SALES_CHANNEL_TYPE_AGENTIC_AI || $writeResult->getOperation() !== EntityWriteResult::OPERATION_INSERT) {
                continue;
            }

            $primaryKey = $writeResult->getPrimaryKey();
            $salesChannelId = \is_array($primaryKey) ? ($primaryKey['id'] ?? null) : $primaryKey;

            if (!\is_string($salesChannelId)) {
                continue;
            }

            $this->productExportProvisioner->provisionForSalesChannel($salesChannelId, $event->getContext());
        }
    }
}
