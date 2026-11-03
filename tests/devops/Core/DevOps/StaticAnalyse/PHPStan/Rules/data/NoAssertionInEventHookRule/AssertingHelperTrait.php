<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoAssertionInEventHookRule;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
trait AssertingHelperTrait
{
    protected function traitCheck(Event $event): void
    {
        static::assertTrue($event->isPropagationStopped());
    }
}
