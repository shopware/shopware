<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Demodata\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Demodata\Event\DemodataRequestCreatedEvent;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * @internal
 */
#[CoversClass(DemodataRequestCreatedEvent::class)]
class DemodataRequestCreatedEventTest extends TestCase
{
    public function testGetter(): void
    {
        $request = new DemodataRequest();
        $context = Context::createDefaultContext();
        $input = new ArrayInput([]);

        $event = new DemodataRequestCreatedEvent(
            $request,
            $context,
            $input
        );

        static::assertEquals($request, $event->getRequest());
        static::assertEquals($context, $event->getContext());
        static::assertEquals($input, $event->getInput());
    }
}
