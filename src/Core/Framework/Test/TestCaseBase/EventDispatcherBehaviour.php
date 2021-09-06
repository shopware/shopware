<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait EventDispatcherBehaviour
{
    private array $registered = [];

    public function addEventListener(EventDispatcherInterface $dispatcher, string $eventName, callable $callback): void
    {
        $this->registered[] = [
            'dispatcher' => $dispatcher,
            'name' => $eventName,
            'callback' => $callback,
        ];

        $dispatcher->addListener($eventName, $callback);
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
