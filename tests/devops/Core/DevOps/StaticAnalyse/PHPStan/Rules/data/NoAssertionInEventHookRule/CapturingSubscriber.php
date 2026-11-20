<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoAssertionInEventHookRule;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class CapturingSubscriber implements EventSubscriberInterface
{
    public ?Event $seen = null;

    public static function getSubscribedEvents(): array
    {
        return [Event::class => 'onEvent'];
    }

    public function onEvent(Event $event): void
    {
        $this->seen = $event;
    }
}
