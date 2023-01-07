<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\ContextTokenAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 */
class ContextTokenStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof ContextTokenAware || isset($stored[ContextTokenAware::CONTEXT_TOKEN])) {
            return $stored;
        }

        $stored[ContextTokenAware::CONTEXT_TOKEN] = $event->getContextToken();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(ContextTokenAware::CONTEXT_TOKEN)) {
            return;
        }

        $storable->setData(ContextTokenAware::CONTEXT_TOKEN, $storable->getStore(ContextTokenAware::CONTEXT_TOKEN));
    }
}
