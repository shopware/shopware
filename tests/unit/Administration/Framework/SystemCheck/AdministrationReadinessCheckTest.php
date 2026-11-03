<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\SystemCheck;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\SystemCheck\AdministrationReadinessCheck;
use Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Test\Stub\Framework\BundleFixture;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetterInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdministrationReadinessCheck::class)]
class AdministrationReadinessCheckTest extends TestCase
{
    private RouterInterface&Stub $router;

    private KernelInterface&Stub $kernel;

    private ViteFileAccessorDecorator&Stub $viteFileAccessor;

    private Filesystem&Stub $filesystem;

    private AdministrationReadinessCheck $check;

    protected function setUp(): void
    {
        $this->router = static::createStub(RouterInterface::class);
        $this->router->method('generate')->willReturn('/admin');
        $this->kernel = static::createStub(KernelInterface::class);
        $this->viteFileAccessor = static::createStub(ViteFileAccessorDecorator::class);
        $this->filesystem = static::createStub(Filesystem::class);
        $this->check = $this->createCheck(static::createStub(ServicesResetterInterface::class));
    }

    public function testName(): void
    {
        static::assertSame('AdministrationReadiness', $this->check->name());
    }

    public function testCategory(): void
    {
        static::assertSame(Category::FEATURE, $this->check->category());
    }

    public function testRunReturnsOkWhenAllChecksPass(): void
    {
        $response = new Response('<html><body><script type="module" src="/bundles/administration/app.js"></script></body></html>');
        $this->kernel->method('handle')->willReturn($response);
        $this->kernel->method('getBundles')->willReturn([]);

        $result = $this->check->run();

        static::assertSame(Status::OK, $result->status);
        static::assertSame('Admininstration is OK', $result->message);
        static::assertTrue($result->healthy);
        static::assertArrayHasKey('indexResponseTime', $result->extra);
        static::assertArrayHasKey('indexPageJsBundlesFound', $result->extra);
        static::assertArrayHasKey('indexPageJsBundles', $result->extra);
        static::assertArrayHasKey('missingArtifactsForJsBundles', $result->extra);
        static::assertSame(1, $result->extra['indexPageJsBundlesFound']);
        static::assertSame(['/bundles/administration/app.js'], $result->extra['indexPageJsBundles']);
        static::assertSame([], $result->extra['missingArtifactsForJsBundles']);
    }

    public function testRunReturnsFailureWhenResponseStatusIsError(): void
    {
        $response = new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);
        $this->kernel->method('handle')->willReturn($response);
        $this->kernel->method('getBundles')->willReturn([]);

        $result = $this->check->run();

        static::assertSame(Status::FAILURE, $result->status);
        static::assertSame('Administration is unhealthy', $result->message);
        static::assertFalse($result->healthy);
    }

    public function testRunReturnsFailureWhenNoJsBundlesFound(): void
    {
        $response = new Response('<html><body></body></html>');
        $this->kernel->method('handle')->willReturn($response);
        $this->kernel->method('getBundles')->willReturn([]);

        $result = $this->check->run();

        static::assertSame(Status::FAILURE, $result->status);
        static::assertFalse($result->healthy);
        static::assertArrayHasKey('indexPageJsBundlesFound', $result->extra);
        static::assertSame(0, $result->extra['indexPageJsBundlesFound']);
    }

    public function testRunReturnsFailureWhenJsBundlesAreMissing(): void
    {
        $response = new Response('<html><body><script type="module" src="/bundles/administration/app.js"></script></body></html>');

        $bundle = new BundleFixture('TestBundle', '/path/to/bundle');

        $this->kernel->method('handle')->willReturn($response);
        $this->kernel->method('getBundles')->willReturn([$bundle]);

        $this->viteFileAccessor->method('getBundleData')->willReturn(['entryPoints' => []]);
        $this->filesystem->method('exists')->willReturn(true);

        $result = $this->check->run();

        static::assertSame(Status::FAILURE, $result->status);
        static::assertFalse($result->healthy);
        static::assertArrayHasKey('missingArtifactsForJsBundles', $result->extra);
        static::assertSame(['TestBundle'], $result->extra['missingArtifactsForJsBundles']);
    }

    public function testRunSkipsNonShopwareBundles(): void
    {
        $response = new Response('<html><body><script type="module" src="/bundles/administration/app.js"></script></body></html>');

        $symfonyBundle = static::createStub(\Symfony\Component\HttpKernel\Bundle\Bundle::class);

        $this->kernel->method('handle')->willReturn($response);
        $this->kernel->method('getBundles')->willReturn([$symfonyBundle]);

        $result = $this->check->run();

        static::assertSame(Status::OK, $result->status);
        static::assertArrayHasKey('missingArtifactsForJsBundles', $result->extra);
        static::assertSame([], $result->extra['missingArtifactsForJsBundles']);
    }

    public function testRunSkipsBundlesWithoutAdministrationPackage(): void
    {
        $response = new Response('<html><body><script type="module" src="/bundles/administration/app.js"></script></body></html>');

        $bundle = static::createStub(Bundle::class);
        $bundle->method('getPath')->willReturn('/path/to/bundle');

        $this->kernel->method('handle')->willReturn($response);
        $this->kernel->method('getBundles')->willReturn([$bundle]);

        $this->viteFileAccessor->method('getBundleData')->willReturn(['entryPoints' => []]);
        $this->filesystem->method('exists')->willReturn(false);

        $result = $this->check->run();

        static::assertSame(Status::OK, $result->status);
        static::assertArrayHasKey('missingArtifactsForJsBundles', $result->extra);
        static::assertSame([], $result->extra['missingArtifactsForJsBundles']);
    }

    public function testRunHandlesNullResponseContent(): void
    {
        $response = static::createStub(Response::class);
        $response->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $response->method('getContent')->willReturn(false);

        $this->kernel->method('handle')->willReturn($response);
        $this->kernel->method('getBundles')->willReturn([]);

        $result = $this->check->run();

        static::assertSame(Status::FAILURE, $result->status);
        static::assertSame(0, $result->extra['indexPageJsBundlesFound']);
    }

    public function testAllowedSystemCheckExecutionContexts(): void
    {
        static::assertTrue($this->check->allowedToRunIn(SystemCheckExecutionContext::PRE_ROLLOUT));
        static::assertFalse($this->check->allowedToRunIn(SystemCheckExecutionContext::RECURRENT));
        static::assertFalse($this->check->allowedToRunIn(SystemCheckExecutionContext::WEB));
        static::assertFalse($this->check->allowedToRunIn(SystemCheckExecutionContext::CLI));
    }

    public function testRunResetsServicesBeforeAndAfterTheRequest(): void
    {
        $resets = 0;
        $resetter = static::createMock(ServicesResetterInterface::class);
        $resetter->expects($this->exactly(2))->method('reset')->willReturnCallback(static function () use (&$resets): void {
            ++$resets;
        });
        $this->kernel->method('handle')->willReturnCallback(static function () use (&$resets): Response {
            static::assertSame(1, $resets);

            return new Response('<script type="module" src="/app.js"></script>');
        });
        $this->kernel->method('getBundles')->willReturn([]);

        static::assertTrue($this->createCheck($resetter)->run()->healthy);
        static::assertSame(2, $resets);
    }

    public function testRunResetsServicesWhenTheRequestThrows(): void
    {
        $exception = new \RuntimeException('Rendering failed');
        $resetter = static::createMock(ServicesResetterInterface::class);
        $resetter->expects($this->exactly(2))->method('reset');
        $this->kernel->method('handle')->willThrowException($exception);

        $this->expectExceptionObject($exception);

        $this->createCheck($resetter)->run();
    }

    private function createCheck(ServicesResetterInterface $servicesResetter): AdministrationReadinessCheck
    {
        return new AdministrationReadinessCheck(
            $this->router,
            $this->kernel,
            $this->viteFileAccessor,
            $this->filesystem,
            new NativeClock(),
            $servicesResetter
        );
    }
}
