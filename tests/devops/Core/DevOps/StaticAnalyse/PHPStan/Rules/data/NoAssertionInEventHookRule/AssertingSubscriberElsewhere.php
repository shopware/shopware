<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoAssertionInEventHookRule;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class AssertingSubscriberElsewhere implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [Event::class => 'onEvent'];
    }

    public function onEvent(Event $event): void
    {
        TestCase::assertTrue($event->isPropagationStopped());
    }
}
