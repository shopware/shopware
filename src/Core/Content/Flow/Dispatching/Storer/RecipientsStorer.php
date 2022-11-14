<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\RecipientsAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 */
class RecipientsStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof RecipientsAware || isset($stored[RecipientsAware::RECIPIENTS])) {
            return $stored;
        }

        $stored[RecipientsAware::RECIPIENTS] = $event->getRecipients();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(RecipientsAware::RECIPIENTS)) {
            return;
        }

        $storable->setData(RecipientsAware::RECIPIENTS, $storable->getStore(RecipientsAware::RECIPIENTS));
    }
}
