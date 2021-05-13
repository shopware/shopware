<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Action;

use Shopware\Core\Content\Flow\Events\FlowActionCollectorEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Event\UserAware;
use Shopware\Core\Framework\Event\WebhookAware;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class FlowActionCollector
{
    private const AWARE_MAPPING = [
        OrderAware::class => 'orderAware',
        CustomerAware::class => 'customerAware',
        WebhookAware::class => 'webhookAware',
        UserAware::class => 'userAware',
        SalesChannelAware::class => 'salesChannelAware',
    ];

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

                continue;
            }
        }
        $this->eventDispatcher->dispatch(
            new FlowActionCollectorEvent($result, $context),
            FlowActionCollectorEvent::NAME
        );

        return $result;
    }

    private function define(FlowAction $service): FlowActionDefinition
    {
        $requirementsName = [];
        foreach ($service->requirements() as $requirement) {
            if (!\array_key_exists($requirement, static::AWARE_MAPPING)) {
                continue;
            }

            $requirementsName[static::AWARE_MAPPING[$requirement]] = $requirement;
        }

        return new FlowActionDefinition(
            $service->getName(),
            $requirementsName,
        );
    }
}
