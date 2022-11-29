<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\NameAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 */
class NameStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof NameAware || isset($stored[NameAware::EVENT_NAME])) {
            return $stored;
        }

        $stored[NameAware::EVENT_NAME] = $event->getName();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(NameAware::EVENT_NAME)) {
            return;
        }

        $storable->setData(NameAware::EVENT_NAME, $storable->getStore(NameAware::EVENT_NAME));
    }
}
