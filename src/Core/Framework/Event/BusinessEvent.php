<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Symfony\Component\EventDispatcher\Event;

class BusinessEvent extends Event
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
}
