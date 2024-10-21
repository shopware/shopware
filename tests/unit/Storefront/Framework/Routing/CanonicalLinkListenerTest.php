<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Routing\CanonicalLinkListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CanonicalLinkListener::class)]
class CanonicalLinkListenerTest extends TestCase
{
    public function testErrorResponseDoesNothing(): void
    {
        $response = new Response(null, Response::HTTP_TEMPORARY_REDIRECT);

        $listener = new CanonicalLinkListener();

        $listener(new BeforeSendResponseEvent(new Request(), $response));

        static::assertCount(2, $response->headers->all());
    }

    public function testLinkHeaderGetsAdded(): void
    {
        $response = new Response();

        $listener = new CanonicalLinkListener();

        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK, 'foo');
        $listener(new BeforeSendResponseEvent($request, $response));

        static::assertCount(3, $response->headers->all());
        static::assertSame('<foo>; rel="canonical"', $response->headers->get('Link'));
    }
}
