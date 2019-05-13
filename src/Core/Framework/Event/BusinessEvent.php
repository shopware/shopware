<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Symfony\Contracts\EventDispatcher\Event;

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
        return $this->event;
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
        return $this->event->getName();
    }

    public function getContext(): Context
    {
        return $this->event->getContext();
    }
}
