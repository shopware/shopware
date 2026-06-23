<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRequestRestorer;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileLoader;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderResult;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileNotFoundSubscriber;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileRequestPathResolver;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileNotFoundSubscriber::class)]
#[CoversClass(SalesChannelFileRequestPathResolver::class)]
class SalesChannelFileNotFoundSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                KernelEvents::EXCEPTION => ['onNotFound', -90],
            ],
            SalesChannelFileNotFoundSubscriber::getSubscribedEvents(),
        );
    }

    public function testItServesSalesChannelFileForUnresolvedNotFoundWithExistingSalesChannelContext(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $request = Request::create('/llms.txt');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['store-api']);

        $loader = $this->createMock(SalesChannelFileLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->with('files/agentic/llms.txt.twig', $context)
            ->willReturn(new SalesChannelFileRenderResult('llms.txt', 'Merchant llms', 'text/plain; charset=utf-8'));

        $event = $this->createExceptionEvent($request);

        $contextRestorer = $this->createMock(SalesChannelContextRequestRestorer::class);
        $contextRestorer
            ->expects($this->once())
            ->method('restore')
            ->with($request)
            ->willReturn($context);

        $this->createSubscriber($loader, $contextRestorer)->onNotFound($event);

        $response = $event->getResponse();
        static::assertInstanceOf(Response::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('text/plain; charset=utf-8', $response->headers->get('content-type'));
        static::assertSame('Merchant llms', $response->getContent());
        static::assertTrue($event->isAllowingCustomResponseCode());
        static::assertTrue($request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE));
        static::assertSame(['store-api'], $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE));
    }

    public function testItServesSalesChannelFileFromWellKnownSubFolder(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $request = Request::create('/.well-known/ucp.json');

        $loader = $this->createMock(SalesChannelFileLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->with('files/agentic/.well-known/ucp.json.twig', $context)
            ->willReturn(new SalesChannelFileRenderResult('.well-known/ucp.json', '{"custom": true}', 'application/json; charset=utf-8'));

        $event = $this->createExceptionEvent($request);

        $contextRestorer = $this->createMock(SalesChannelContextRequestRestorer::class);
        $contextRestorer
            ->expects($this->once())
            ->method('restore')
            ->with($request)
            ->willReturn($context);

        $this->createSubscriber($loader, $contextRestorer)->onNotFound($event);

        $response = $event->getResponse();
        static::assertInstanceOf(Response::class, $response);
        static::assertSame('{"custom": true}', $response->getContent());
    }

    public function testItUsesContextResolverForCandidateFilePath(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $request = Request::create('/llms.txt');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, 'sales-channel-id');

        $contextRestorer = $this->createMock(SalesChannelContextRequestRestorer::class);
        $contextRestorer
            ->expects($this->once())
            ->method('restore')
            ->with($request)
            ->willReturn($context);

        $loader = $this->createMock(SalesChannelFileLoader::class);
        $loader
            ->expects($this->once())
            ->method('load')
            ->with('files/agentic/llms.txt.twig', $context)
            ->willReturn(new SalesChannelFileRenderResult('llms.txt', 'Merchant llms', 'text/plain; charset=utf-8'));

        $event = $this->createExceptionEvent($request);

        $this->createSubscriber($loader, $contextRestorer)->onNotFound($event);

        static::assertSame('Merchant llms', $event->getResponse()?->getContent());
    }

    public function testItReturnsEarlyWithoutSalesChannelContext(): void
    {
        $loader = $this->createMock(SalesChannelFileLoader::class);
        $loader
            ->expects($this->never())
            ->method('load');

        $event = $this->createExceptionEvent(Request::create('/llms.txt'));

        $contextRestorer = $this->createMock(SalesChannelContextRequestRestorer::class);
        $contextRestorer
            ->expects($this->once())
            ->method('restore')
            ->willReturn(null);

        $this->createSubscriber($loader, $contextRestorer)->onNotFound($event);

        static::assertNull($event->getResponse());
    }

    public function testItReturnsEarlyForRoutedNotFound(): void
    {
        $request = Request::create('/llms.txt');
        $request->attributes->set('_route', 'frontend.example');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));

        $loader = $this->createMock(SalesChannelFileLoader::class);
        $loader
            ->expects($this->never())
            ->method('load');

        $event = $this->createExceptionEvent($request);

        $this->createSubscriber($loader)->onNotFound($event);

        static::assertNull($event->getResponse());
    }

    public function testItReturnsEarlyForInvalidPublicPath(): void
    {
        $request = Request::create('/folder/file');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));

        $loader = $this->createMock(SalesChannelFileLoader::class);
        $loader
            ->expects($this->never())
            ->method('load');

        $event = $this->createExceptionEvent($request);

        $this->createSubscriber($loader)->onNotFound($event);

        static::assertNull($event->getResponse());
    }

    public function testItReturnsEarlyForNonNotFoundExceptions(): void
    {
        $request = Request::create('/llms.txt');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $this->createMock(SalesChannelContext::class));

        $loader = $this->createMock(SalesChannelFileLoader::class);
        $loader
            ->expects($this->never())
            ->method('load');

        $event = $this->createExceptionEvent($request, new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR));

        $this->createSubscriber($loader)->onNotFound($event);

        static::assertNull($event->getResponse());
    }

    private function createExceptionEvent(Request $request, ?\Throwable $throwable = null): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createMock(Kernel::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $throwable ?? new NotFoundHttpException(),
        );
    }

    private function createSubscriber(SalesChannelFileLoader $loader, ?SalesChannelContextRequestRestorer $contextRestorer = null): SalesChannelFileNotFoundSubscriber
    {
        if ($contextRestorer === null) {
            $contextRestorer = $this->createMock(SalesChannelContextRequestRestorer::class);
            $contextRestorer
                ->expects($this->never())
                ->method('restore');
        }

        return new SalesChannelFileNotFoundSubscriber(
            $loader,
            new SalesChannelFileRequestPathResolver(),
            $contextRestorer,
        );
    }
}
