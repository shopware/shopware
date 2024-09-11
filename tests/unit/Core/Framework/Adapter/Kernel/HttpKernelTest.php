<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Kernel\HttpKernel;
use Shopware\Core\Framework\Routing\CanonicalRedirectService;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Storefront\Framework\Routing\Exception\SalesChannelMappingException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * @internal
 */
#[CoversClass(HttpKernel::class)]
class HttpKernelTest extends TestCase
{
    private ControllerResolverInterface&MockObject $controllerResolver;

    protected function setUp(): void
    {
        $this->controllerResolver = $this->createMock(ControllerResolverInterface::class);
        $this->controllerResolver
            ->method('getController')
            ->willReturn(static function (): Response {
                return new Response();
            });
    }

    public function testNoTransformOnErrorPages(): void
    {
        $requestTransformer = $this->createMock(RequestTransformerInterface::class);
        $requestTransformer
            ->expects(static::never())
            ->method('transform');

        $kernel = new HttpKernel(
            new EventDispatcher(),
            $this->controllerResolver,
            $this->createMock(RequestStack::class),
            $this->createMock(ArgumentResolverInterface::class),
            $requestTransformer,
            $this->createMock(CanonicalRedirectService::class)
        );

        $request = new Request();
        $request->attributes->set('exception', new \Exception());

        $kernel->handle($request);
    }

    public function testTransformThrowsUnknownException(): void
    {
        $requestTransformer = $this->createMock(RequestTransformerInterface::class);
        $requestTransformer
            ->expects(static::once())
            ->method('transform')
            ->willThrowException(new \Exception());

        $kernel = new HttpKernel(
            new EventDispatcher(),
            $this->controllerResolver,
            $this->createMock(RequestStack::class),
            $this->createMock(ArgumentResolverInterface::class),
            $requestTransformer,
            $this->createMock(CanonicalRedirectService::class)
        );

        $request = new Request();

        $this->expectException(\Exception::class);

        $kernel->handle($request);
    }

    public function testTransformThrowsDomainException(): void
    {
        $requestTransformer = $this->createMock(RequestTransformerInterface::class);
        $requestTransformer
            ->expects(static::once())
            ->method('transform')
            ->willThrowException(new SalesChannelMappingException('test'));

        $dispatcher = new EventDispatcher();

        $dispatcher->addListener('kernel.exception', static function (ExceptionEvent $event): void {
            $event->setResponse(new Response('event'));
        });

        $kernel = new HttpKernel(
            $dispatcher,
            $this->controllerResolver,
            $this->createMock(RequestStack::class),
            $this->createMock(ArgumentResolverInterface::class),
            $requestTransformer,
            $this->createMock(CanonicalRedirectService::class)
        );

        $request = new Request();

        $response = $kernel->handle($request);

        static::assertSame('event', $response->getContent());
    }

    public function testHandleNormalRequest(): void
    {
        $requestTransformer = $this->createMock(RequestTransformerInterface::class);
        $requestTransformer
            ->expects(static::once())
            ->method('transform')
            ->willReturnArgument(0);

        $kernel = new HttpKernel(
            new EventDispatcher(),
            $this->controllerResolver,
            $this->createMock(RequestStack::class),
            $this->createMock(ArgumentResolverInterface::class),
            $requestTransformer,
            $this->createMock(CanonicalRedirectService::class)
        );

        $request = new Request();

        $kernel->handle($request);
    }

    public function testHandleRedirect(): void
    {
        $requestTransformer = $this->createMock(RequestTransformerInterface::class);
        $requestTransformer
            ->expects(static::once())
            ->method('transform')
            ->willReturnArgument(0);

        $canonicalRedirectService = $this->createMock(CanonicalRedirectService::class);
        $canonicalRedirectService
            ->method('getRedirect')
            ->willReturn(new RedirectResponse('/foo'));

        $kernel = new HttpKernel(
            new EventDispatcher(),
            $this->controllerResolver,
            $this->createMock(RequestStack::class),
            $this->createMock(ArgumentResolverInterface::class),
            $requestTransformer,
            $canonicalRedirectService
        );

        $request = new Request();

        $response = $kernel->handle($request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/foo', $response->getTargetUrl());
    }
}
