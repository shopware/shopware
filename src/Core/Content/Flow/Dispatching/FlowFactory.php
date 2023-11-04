<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\Dispatching\Storer\FlowStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('business-ops')]
class FlowFactory
{
    /**
     * @param FlowStorer[] $storer
     */
    public function __construct(private $storer)
    {
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
        // @deprecated tag:v6.6.0 - Remove `silent` call and keep inner function
        return Feature::silent('v6.6.0.0', function () use ($name, $context, $stored, $data) {
            $flow = new StorableFlow($name, $context, $stored, $data);

            foreach ($this->storer as $storer) {
                $storer->restore($flow);
            }

            return $flow;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function getStored(FlowEventAware $event): array
    {
        // @deprecated tag:v6.6.0 - Remove `silent` call and keep inner function
        return Feature::silent('v6.6.0.0', function () use ($event) {
            $stored = [];
            foreach ($this->storer as $storer) {
                $stored = $storer->store($event, $stored);
            }

            return $stored;
        });
    }
}
