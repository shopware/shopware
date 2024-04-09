<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Framework\Routing\Exception\ErrorRedirectRequestEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Routing\Exception\ErrorRedirectRequestEvent
 */
class ErrorRedirectRequestEventTest extends TestCase
{
    public function testEvent(): void
    {
        $request = new Request();
        $context = Context::createDefaultContext();
        $exception = new \Exception();

        $event = new ErrorRedirectRequestEvent($request, $exception, $context);

        static::assertSame($context, $event->getContext());
        static::assertSame($exception, $event->getException());
        static::assertSame($request, $event->getRequest());
    }
}
