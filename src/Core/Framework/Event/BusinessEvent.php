<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Feature;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
 */
class BusinessEvent extends Event implements BusinessEventInterface
{
    /**
     * @var BusinessEventInterface
     */
    private $event;

    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $actionName;

    public function __construct(string $actionName, BusinessEventInterface $event, ?array $config = [])
    {
        $this->actionName = $actionName;
        $this->event = $event;
        $this->config = $config ?? [];
    }

    public function getEvent(): BusinessEventInterface
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'FlowEvent')
        );

        return $this->event;
    }

    public function getConfig(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'FlowEvent')
        );

        return $this->config;
    }

    public function getActionName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'FlowEvent')
        );

        return $this->actionName;
    }

    public static function getAvailableData(): EventDataCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'FlowEvent')
        );

        return new EventDataCollection();
    }

    public function getName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'FlowEvent')
        );

        return $this->event->getName();
    }

    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'FlowEvent')
        );

        return $this->event->getContext();
    }
}
