<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Symfony\Contracts\EventDispatcher\Event;

class FlowLogEvent extends Event implements FlowEventAware
{
    public const NAME = 'flow.log';

    private string $name;

    private FlowEventAware $event;

    private array $config;

    public function __construct(string $name, FlowEventAware $event, ?array $config = [])
    {
        $this->name = $name;
        $this->event = $event;
        $this->config = $config ?? [];
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEvent(): FlowEventAware
    {
        return $this->event;
    }

    public function getContext(): Context
    {
        return $this->event->getContext();
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
