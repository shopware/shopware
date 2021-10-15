<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Api;

use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Events\FlowActionCollectorEvent;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class FlowActionCollector
{
    protected iterable $actions;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(iterable $actions, EventDispatcherInterface $eventDispatcher)
    {
        $this->actions = $actions;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function collect(Context $context): FlowActionCollectorResponse
    {
        $result = new FlowActionCollectorResponse();
        foreach ($this->actions as $service) {
            if (!$service instanceof FlowAction) {
                continue;
            }

            $definition = $this->define($service);

            if (!$result->has($definition->getName())) {
                $result->set($definition->getName(), $definition);
            }
        }
        $this->eventDispatcher->dispatch(new FlowActionCollectorEvent($result, $context));

        return $result;
    }

    private function define(FlowAction $service): FlowActionDefinition
    {
        $requirementsName = [];
        foreach ($service->requirements() as $key => $requirement) {
            $requirementsName[$key] = $requirement;
        }

        return new FlowActionDefinition(
            $service::getName(),
            $requirementsName,
        );
    }
}
