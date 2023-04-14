<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

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
        int $priority = 0
    ): void {
        /** @var callable(object): void $callback - Specify generic callback interface callers can provide more specific implementations */
        $this->registered[] = [
            'dispatcher' => $dispatcher,
            'name' => $eventName,
            'callback' => $callback,
        ];

        $dispatcher->addListener($eventName, $callback, $priority);
    }

    /**
     * @after
     */
    public function resetEventDispatcher(): void
    {
        foreach ($this->registered as $item) {
            $item['dispatcher']->removeListener($item['name'], $item['callback']);
        }

        $this->registered = [];
    }
}
