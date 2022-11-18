<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Api;

use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Events\FlowActionCollectorEvent;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class FlowActionCollector
{
    /**
     * @var iterable<FlowAction>
     */
    protected iterable $actions;

    private EventDispatcherInterface $eventDispatcher;

    private EntityRepository $appFlowActionRepo;

    /**
     * @internal
     *
     * @param iterable<FlowAction> $actions
     */
    public function __construct(
        iterable $actions,
        EventDispatcherInterface $eventDispatcher,
        EntityRepository $appFlowActionRepo
    ) {
        $this->actions = $actions;
        $this->eventDispatcher = $eventDispatcher;
        $this->appFlowActionRepo = $appFlowActionRepo;
    }

    public function collect(Context $context): FlowActionCollectorResponse
    {
        $result = new FlowActionCollectorResponse();

        $result = $this->fetchAppActions($result, $context);

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

    private function fetchAppActions(FlowActionCollectorResponse $result, Context $context): FlowActionCollectorResponse
    {
        $criteria = new Criteria();
        $appActions = $this->appFlowActionRepo->search($criteria, $context)->getEntities();

        /** @var AppFlowActionEntity $action */
        foreach ($appActions as $action) {
            $definition = new FlowActionDefinition(
                $action->getName(),
                $action->getRequirements(),
                $action->getDelayable()
            );

            if (!$result->has($definition->getName())) {
                $result->set($definition->getName(), $definition);
            }
        }

        return $result;
    }

    private function define(FlowAction $service): FlowActionDefinition
    {
        $requirementsName = [];
        foreach ($service->requirements() as $requirement) {
            /** @deprecated tag:v6.5.0 will be removed in v6.5.0 */
            if (!Feature::isActive('v6.5.0.0')) {
                $requirementsName[] = $requirement;
            }

            $className = explode('\\', $requirement);
            $requirementsName[] = lcfirst(end($className));
        }

        $delayable = false;
        if ($service instanceof DelayableAction) {
            $delayable = true;
        }

        return new FlowActionDefinition(
            $service::getName(),
            $requirementsName,
            $delayable
        );
    }
}
