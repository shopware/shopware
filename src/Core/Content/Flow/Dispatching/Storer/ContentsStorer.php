<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\ContentsAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 */
class ContentsStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof ContentsAware || isset($stored[ContentsAware::CONTENTS])) {
            return $stored;
        }

        $stored[ContentsAware::CONTENTS] = $event->getContents();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(ContentsAware::CONTENTS)) {
            return;
        }

        $storable->setData(ContentsAware::CONTENTS, $storable->getStore(ContentsAware::CONTENTS));
    }
}
