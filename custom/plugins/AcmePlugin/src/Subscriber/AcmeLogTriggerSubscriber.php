<?php declare(strict_types=1);

namespace Acme\AcmePlugin\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Validates that products written to the database carry the required
 * Acme SKU custom field. Logs a structured warning when the field is absent
 * so operations teams can identify and correct incomplete imports.
 */
class AcmeLogTriggerSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();
            if (empty($payload['customFields']['acme_sku'] ?? null)) {
                // Log at ERROR — missing acme_sku blocks ERP sync and causes downstream failures.
                $this->logger->error('AcmePlugin: product written without acme_sku — ERP sync will fail', [
                    'product_id' => $result->getPrimaryKey(),
                    'plugin' => 'AcmePlugin',
                    'field' => 'customFields.acme_sku',
                    'action_required' => 'Set acme_sku via Admin API or correct the import payload',
                    'impact' => 'ERP sync job will reject this product and raise a reconciliation alert',
                ]);
            }
        }
    }
}
