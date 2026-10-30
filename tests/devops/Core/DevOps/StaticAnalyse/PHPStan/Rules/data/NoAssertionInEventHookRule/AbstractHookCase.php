<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoAssertionInEventHookRule;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
abstract class AbstractHookCase extends TestCase
{
    protected function inheritedCheck(Event $event): void
    {
        static::assertNotNull($event);
    }
}
