<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait EventDispatcherBehaviour
{
    /**
     * @var array<array{'dispatcher': EventDispatcherInterface, 'name': string, 'callback': callable}>
     */
    private array $registered = [];

    public function addEventListener(
        EventDispatcherInterface $dispatcher,
        string $eventName,
        callable $callback,
        int $priority = 0
    ): void {
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
