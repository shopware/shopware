<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('core')]
class EventDispatcherWrapper
{
    /**
     * @param callable $callback
     */
    public function __construct(
        private $callback,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly bool $once,
        private readonly string $eventName
    ) {
    }

    public function __invoke(): void
    {
        $callback = $this->callback;

        $callback(...\func_get_args());

        if ($this->once) {
            $this->dispatcher->removeListener($this->eventName, $this);
        }
    }
}
