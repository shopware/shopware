<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\EventListener\JsonRequestTransformerListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(JsonRequestTransformerListener::class)]
class JsonRequestTransformerListenerTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = JsonRequestTransformerListener::getSubscribedEvents();

        static::assertCount(1, $events);
        static::assertSame(['onRequest', 128], $events['kernel.request']);
    }

    public function testXmlRequestDoesNothing(): void
    {
        $listener = new JsonRequestTransformerListener();
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), new Request([], [], [], [], [], [], '<xml></xml>'), HttpKernelInterface::MAIN_REQUEST);

        $listener->onRequest($event);

        static::assertSame('<xml></xml>', $event->getRequest()->getContent());
    }

    public function testJsonValid(): void
    {
        $listener = new JsonRequestTransformerListener();
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), new Request([], [], [], [], [], ['HTTP_Content-Type' => 'application/json'], '{"yes":1}'), HttpKernelInterface::MAIN_REQUEST);

        $listener->onRequest($event);

        static::assertSame(['yes' => 1], $event->getRequest()->request->all());
    }

    public function testJsonInvalid(): void
    {
        $listener = new JsonRequestTransformerListener();
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), new Request([], [], [], [], [], ['HTTP_Content-Type' => 'application/json'], '{'), HttpKernelInterface::MAIN_REQUEST);

        static::expectException(BadRequestHttpException::class);
        $listener->onRequest($event);
    }
}
