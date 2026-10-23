<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetterInterface;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelDomainUtil::class)]
class SalesChannelDomainUtilTest extends TestCase
{
    private KernelInterface&Stub $kernel;

    private RouterInterface&Stub $router;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->kernel = static::createStub(KernelInterface::class);
        $this->router = static::createStub(RouterInterface::class);
        $this->requestStack = new RequestStack();
    }

    public function testRunAsSalesChannelRequest(): void
    {
        $this->requestStack->push(new Request([], [], [
            SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
        ]));

        $util = $this->getUtil();

        $result = $util->runAsSalesChannelRequest(static function () {
            return new Result(
                'test',
                Status::OK,
                'Test completed successfully'
            );
        });

        static::assertSame('test', $result->name);
        static::assertSame(Status::OK, $result->status);

        $request = $this->requestStack->getMainRequest();
        static::assertInstanceOf(Request::class, $request);
        static::assertTrue($request->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, false));
    }

    public function testRunAsSalesChannelRequestWithoutMainRequest(): void
    {
        $util = $this->getUtil();

        $result = $util->runAsSalesChannelRequest(static function () {
            return new Result(
                'test',
                Status::OK,
                'Test completed successfully'
            );
        });

        static::assertSame('test', $result->name);
        static::assertSame(Status::OK, $result->status);
        static::assertNull($this->requestStack->getMainRequest());
    }

    public function testRunWhileTrustingAllHosts(): void
    {
        Request::setTrustedHosts(['example.com']);

        $util = $this->getUtil();

        $result = $util->runWhileTrustingAllHosts(static function () {
            // check that trusted hosts are empty during the callback
            static::assertSame([], Request::getTrustedHosts());

            return new Result(
                'test',
                Status::OK,
                'Test completed successfully'
            );
        });

        static::assertSame('test', $result->name);
        static::assertSame(Status::OK, $result->status);

        // setTrustedHosts adds '{' and '}i' around the host
        static::assertSame(['{example.com}i'], Request::getTrustedHosts());

        // Reset trusted hosts to avoid leaking state
        Request::setTrustedHosts([]);
    }

    public function testGenerateDomainUrl(): void
    {
        $url = 'https://example.com';
        $routeName = 'test_route';
        $parameters = ['param1' => 'value1', 'param2' => 'value2'];

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with($routeName, $parameters)
            ->willReturn('/test/path');

        $util = $this->getUtil($router);

        $resultUrl = $util->generateDomainUrl($url, $routeName, $parameters);

        static::assertSame('https://example.com/test/path', $resultUrl);
    }

    public function testCreateEmptyResult(): void
    {
        $util = $this->getUtil();

        $result = $util->createEmptyResult('test', 'This is a test message');

        static::assertSame('test', $result->name);
        static::assertSame(Status::SKIPPED, $result->status);
        static::assertSame('This is a test message', $result->message);
        static::assertTrue($result->healthy);
    }

    public function testHandleRequestWithRedirects(): void
    {
        $this->kernel->method('handle')->willReturnOnConsecutiveCalls(
            new RedirectResponse('http://localhost/seo', Response::HTTP_MOVED_PERMANENTLY),
            new RedirectResponse('http://localhost/product/123', Response::HTTP_MOVED_PERMANENTLY),
            new Response(status: Response::HTTP_OK),
        );

        $util = $this->getUtil();
        $request = new Request();

        $result = $util->handleRequest($request);
        static::assertSame('http://localhost/product/123', $result->storefrontUrl);
        static::assertSame(Response::HTTP_OK, $result->responseCode);
    }

    public function testHandleRequestsDetectsLoop(): void
    {
        $this->kernel->method('handle')->willReturnOnConsecutiveCalls(
            ...array_fill(0, 6, new RedirectResponse('http://localhost/product/123', Response::HTTP_MOVED_PERMANENTLY)),
        );

        $util = $this->getUtil();
        $request = new Request();

        $result = $util->handleRequest($request);
        static::assertSame('http://localhost/product/123', $result->storefrontUrl);
        static::assertSame(Response::HTTP_LOOP_DETECTED, $result->responseCode);
    }

    public function testHandleRequestRecordsAnExceptionAsFailedResult(): void
    {
        $this->kernel->method('handle')->willThrowException(new \RuntimeException('Could not find product'));

        $result = $this->getUtil()->handleRequest(Request::create('http://localhost/product/123'));

        static::assertSame('http://localhost/product/123', $result->storefrontUrl);
        static::assertSame(Response::HTTP_BAD_REQUEST, $result->responseCode);
        static::assertSame('Could not find product', $result->errorMessage);
    }

    public function testCreateExceptionResult(): void
    {
        $exception = new \RuntimeException('Currency not found');

        // without a request in the context, the log line has to name the page that could not be probed on its own
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Error during systemcheck: "Currency not found"', ['exception' => $exception, 'url' => 'http://localhost', 'request' => null]);

        $result = $this->getUtil(logger: $logger)->createExceptionResult('http://localhost', $exception);

        static::assertSame('http://localhost', $result->storefrontUrl);
        static::assertSame(Response::HTTP_BAD_REQUEST, $result->responseCode);
        static::assertSame(0.0, $result->responseTime);
        static::assertSame('Currency not found', $result->errorMessage);
    }

    public function testRequestsAndRedirectsUseFreshTwigGlobalsAndPreserveTheOuterRequest(): void
    {
        $outerRequest = Request::create('http://localhost/api/_info/system-health-check');
        $this->requestStack->push($outerRequest);
        $loader = new ArrayLoader([
            'page' => '{% include "logo" only %}',
            'logo' => '{{ themeId }}',
            '@A/logo' => '{{ themeId }}',
            '@B/logo' => '{{ themeId }}',
            '@C/logo' => '{{ themeId }}',
        ]);
        $twig = new TwigEnvironment($loader);
        $twig->addExtension(new class($this->requestStack) extends AbstractExtension implements GlobalsInterface {
            public function __construct(private readonly RequestStack $requestStack)
            {
            }

            public function getGlobals(): array
            {
                return ['themeId' => $this->requestStack->getCurrentRequest()?->attributes->get('themeId')];
            }
        });

        // An Administration render initializes the same Twig environment without Storefront globals.
        $twig->render('page');

        $hierarchyBuilder = static::createStub(NamespaceHierarchyBuilder::class);
        $hierarchyBuilder->method('buildHierarchy')->willReturnCallback(fn (): array => [
            ltrim($this->requestStack->getCurrentRequest()?->getPathInfo() ?? '', '/') => 0,
        ]);
        $finder = new TemplateFinder($twig, $loader, '', $hierarchyBuilder, new TemplateScopeDetector($this->requestStack));

        $renderedThemes = [];
        $this->kernel->method('handle')->willReturnCallback(function (Request $request) use ($twig, $finder, &$renderedThemes): Response {
            $request->attributes->set('themeId', $request->getPathInfo());
            $this->requestStack->push($request);
            try {
                static::assertSame('@' . ltrim($request->getPathInfo(), '/') . '/logo', $finder->find('@Storefront/logo'));
                $renderedThemes[] = $twig->render('page');
            } finally {
                $this->requestStack->pop();
            }

            return $request->getPathInfo() === '/A' ? new RedirectResponse('http://localhost/B') : new Response();
        });

        $resetter = new ServicesResetter(new \ArrayIterator(['twig' => $twig, 'finder' => $finder]), ['twig' => 'reset', 'finder' => 'reset']);
        $util = $this->getUtil(servicesResetter: $resetter);
        $util->handleRequest(Request::create('http://localhost/A'));
        $util->handleRequest(Request::create('http://localhost/C'));

        static::assertSame(['/A', '/B', '/C'], $renderedThemes);
        static::assertSame($outerRequest, $this->requestStack->getCurrentRequest());
        static::assertSame('', $twig->render('page'));
    }

    public function testHandleRequestResetsServicesAfterAnException(): void
    {
        $resets = 0;
        $resetter = static::createMock(ServicesResetterInterface::class);
        $resetter->expects($this->exactly(4))->method('reset')->willReturnCallback(static function () use (&$resets): void {
            ++$resets;
        });
        $this->kernel->method('handle')->willReturnCallback(static function () use (&$resets): Response {
            if ($resets === 1) {
                throw new \RuntimeException('Rendering failed');
            }

            static::assertSame(3, $resets);

            return new Response();
        });
        $util = $this->getUtil(servicesResetter: $resetter);

        static::assertSame(Response::HTTP_BAD_REQUEST, $util->handleRequest(Request::create('http://localhost/failing'))->responseCode);
        static::assertSame(Response::HTTP_OK, $util->handleRequest(Request::create('http://localhost/healthy'))->responseCode);
        static::assertSame(4, $resets);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFailedProbeClosesItsNativeSessionBeforeTheNextProbe(): void
    {
        $this->kernel->method('handle')->willReturnCallback(static function (Request $request): Response {
            $session = new Session(new NativeSessionStorage(['use_cookies' => false, 'cache_limiter' => '']));
            $request->setSession($session);
            $session->start();

            if ($request->getPathInfo() === '/failing') {
                throw new \RuntimeException('Rendering failed after starting the session');
            }

            return new Response();
        });
        $resetter = new ServicesResetter(new \ArrayIterator(['session_listener' => new SessionListener()]), ['session_listener' => 'reset']);
        $util = $this->getUtil(servicesResetter: $resetter);

        try {
            $failed = $util->handleRequest(Request::create('http://localhost/failing'));
            $sessionStatusAfterFailure = session_status();
            $healthy = $util->handleRequest(Request::create('http://localhost/healthy'));

            static::assertSame(Response::HTTP_BAD_REQUEST, $failed->responseCode);
            static::assertSame('Rendering failed after starting the session', $failed->errorMessage);
            static::assertSame(\PHP_SESSION_NONE, $sessionStatusAfterFailure);
            static::assertSame(Response::HTTP_OK, $healthy->responseCode);
            static::assertSame(\PHP_SESSION_NONE, session_status());
        } finally {
            if (session_status() === \PHP_SESSION_ACTIVE) {
                session_abort();
            }
        }
    }

    private function getUtil(?RouterInterface $router = null, ?LoggerInterface $logger = null, ?ServicesResetterInterface $servicesResetter = null): SalesChannelDomainUtil
    {
        return new SalesChannelDomainUtil(
            $router ?? $this->router,
            $this->requestStack,
            $this->kernel,
            $logger ?? new NullLogger(),
            new NativeClock(),
            $servicesResetter ?? static::createStub(ServicesResetterInterface::class)
        );
    }
}
