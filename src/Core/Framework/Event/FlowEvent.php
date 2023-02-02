<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Symfony\Contracts\EventDispatcher\Event;

class FlowEvent extends Event
{
    private FlowState $state;

    /**
     * @var array<string, mixed>
     */
    private $config;

    private string $actionName;

    /**
     * @param array<string, mixed>|null $config
     */
    public function __construct(string $actionName, FlowState $state, ?array $config = [])
    {
        $this->actionName = $actionName;
        $this->state = $state;
        $this->config = $config ?? [];
    }

    public function getEvent(): FlowEventAware
    {
        if (!$this->state->event) {
            throw FlowException::methodNotCompatible('getEvent()', self::class);
        }

        return $this->state->event;
    }

    /**
     * @return array<string, mixed>
     */
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
        if (!$this->state->event) {
            throw FlowException::methodNotCompatible('getName()', self::class);
        }

        return $this->state->event->getName();
    }

    public function getContext(): Context
    {
        if (!$this->state->event) {
            throw FlowException::methodNotCompatible('getContext()', self::class);
        }

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
