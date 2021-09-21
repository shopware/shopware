<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Symfony\Contracts\EventDispatcher\Event;

class FlowEvent extends Event
{
    private FlowState $state;

    private array $config;

    private string $actionName;

    public function __construct(string $actionName, FlowState $state, ?array $config = [])
    {
        $this->actionName = $actionName;
        $this->state = $state;
        $this->config = $config ?? [];
    }

    public function getEvent(): FlowEventAware
    {
        return $this->state->event;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getActionName(): string
    {
        return $this->actionName;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return $this->state->event->getName();
    }

    public function getContext(): Context
    {
        return $this->state->event->getContext();
    }

    public function getFlowState(): FlowState
    {
        return $this->state;
    }

    public function stop(): void
    {
        $this->state->stop = true;
    }
}
