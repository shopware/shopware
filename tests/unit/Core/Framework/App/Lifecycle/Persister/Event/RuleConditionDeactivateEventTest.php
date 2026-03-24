<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\Persister\Event\RuleConditionDeactivateEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RuleConditionDeactivateEvent::class)]
class RuleConditionDeactivateEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $appId = 'test-app-id';
        $context = Context::createDefaultContext();

        $event = new RuleConditionDeactivateEvent($appId, $context);

        static::assertSame($appId, $event->getAppId());
        static::assertSame($context, $event->getContext());
    }
}
