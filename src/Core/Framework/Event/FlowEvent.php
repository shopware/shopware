<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Feature;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.5.0 - Will be removed, use `FlowAction::handleFlow()` instead
 */
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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $this->actionName = $actionName;
        $this->state = $state;
        $this->config = $config ?? [];
    }

    public function getEvent(): FlowEventAware
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->state->event;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->config;
    }

    public function getActionName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->actionName;
    }

    public static function getAvailableData(): EventDataCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return new EventDataCollection();
    }

    public function getName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->state->event->getName();
    }

    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->state->event->getContext();
    }

    public function getFlowState(): FlowState
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return $this->state;
    }

    public function stop(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $this->state->stop = true;
    }
}
