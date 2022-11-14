<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\Dispatching\Storer\FlowStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 *
 * @internal
 */
class FlowFactory
{
    /**
     * @var FlowStorer[]
     */
    private iterable $storer;

    /**
     * @param FlowStorer[] $storer
     */
    public function __construct($storer)
    {
        $this->storer = $storer;
    }

    public function create(FlowEventAware $event): StorableFlow
    {
        $stored = $this->getStored($event);

        return $this->restore($event->getName(), $event->getContext(), $stored);
    }

    /**
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $data
     */
    public function restore(string $name, Context $context, array $stored = [], array $data = []): StorableFlow
    {
        $flow = new StorableFlow($name, $context, $stored, $data);

        foreach ($this->storer as $storer) {
            $storer->restore($flow);
        }

        return $flow;
    }

    /**
     * @return array<string, mixed>
     */
    private function getStored(FlowEventAware $event): array
    {
        $stored = [];
        foreach ($this->storer as $storer) {
            $stored = $storer->store($event, $stored);
        }

        return $stored;
    }
}
