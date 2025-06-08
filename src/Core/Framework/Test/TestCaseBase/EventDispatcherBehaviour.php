<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\Attributes\After;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait EventDispatcherBehaviour
{
    /**
     * @var array<array{'dispatcher': EventDispatcherInterface, 'name': string, 'callback': callable(object): void}>
     */
    private array $registered = [];

    public function addEventListener(
        EventDispatcherInterface $dispatcher,
        string $eventName,
        callable $callback,
        int $priority = 0,
        bool $once = false
    ): void {
        $instance = new EventDispatcherWrapper($callback, $dispatcher, $once, $eventName);

        /** @var callable(object): void $callback - Specify generic callback interface callers can provide more specific implementations */
        $this->registered[] = [
            'dispatcher' => $dispatcher,
            'name' => $eventName,
            'callback' => $instance,
        ];

        $dispatcher->addListener($eventName, $instance, $priority);
    }

    #[After]
    public function resetEventDispatcher(): void
    {
        foreach ($this->registered as $item) {
            $item['dispatcher']->removeListener($item['name'], $item['callback']);
        }

        $this->registered = [];
    }
}
