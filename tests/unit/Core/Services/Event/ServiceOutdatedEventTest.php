<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Services\Event\ServiceOutdatedEvent;

/**
 * @internal
 */
#[CoversClass(ServiceOutdatedEvent::class)]
class ServiceOutdatedEventTest extends TestCase
{
    public function testAccessors(): void
    {
        $context = new Context(new SystemSource());
        $e = new ServiceOutdatedEvent('MyCoolService', $context);

        static::assertSame('MyCoolService', $e->serviceName);
        static::assertSame($context, $e->getContext());
    }
}
