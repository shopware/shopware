<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule\Collector;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ConditionCollector
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return string[]
     */
    public function collect(): array
    {
        $event = new CollectConditionEvent();
        $this->eventDispatcher->dispatch(CollectConditionEvent::NAME, $event);

        return $event->getClasses();
    }
}
