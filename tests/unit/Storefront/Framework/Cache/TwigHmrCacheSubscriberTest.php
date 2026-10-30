<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Cache\TwigHmrCacheSubscriber;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TwigHmrCacheSubscriber::class)]
class TwigHmrCacheSubscriberTest extends TestCase
{
    private string $twigCacheDir;

    protected function setUp(): void
    {
        $this->twigCacheDir = sys_get_temp_dir() . '/twig-hmr-' . bin2hex(random_bytes(4)) . '/twig';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(\dirname($this->twigCacheDir));
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                HttpCacheKeyEvent::class => 'bypassCache',
                KernelEvents::REQUEST => ['resetTwigCache', 4096],
            ],
            TwigHmrCacheSubscriber::getSubscribedEvents()
        );
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('provideRequests')]
    public function testBypassCache(bool $debug, array $headers, bool $expectedCacheable): void
    {
        $request = new Request();
        $request->headers->add($headers);
        $event = new HttpCacheKeyEvent($request);

        (new TwigHmrCacheSubscriber($debug, $this->twigCacheDir))->bypassCache($event);

        static::assertSame($expectedCacheable, $event->isCacheable);
    }

    /**
     * @return iterable<string, array{bool, array<string, string>, bool}>
     */
    public static function provideRequests(): iterable
    {
        yield 'hot reload request in debug mode' => [true, ['sw-twig-hmr' => '1'], false];
        yield 'regular request in debug mode' => [true, [], true];
        yield 'other header value in debug mode' => [true, ['sw-twig-hmr' => '0'], true];
        yield 'hot reload request without debug mode' => [false, ['sw-twig-hmr' => '1'], true];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('provideResetRequests')]
    public function testResetTwigCache(bool $debug, array $headers, int $requestType, bool $expectedRemoved): void
    {
        $filesystem = new Filesystem();
        $filesystem->dumpFile($this->twigCacheDir . '/ab/template.php', '<?php');

        $request = new Request();
        $request->headers->add($headers);
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $requestType);

        (new TwigHmrCacheSubscriber($debug, $this->twigCacheDir))->resetTwigCache($event);

        // the renamed directory is removed as well
        static::assertSame($expectedRemoved ? ['.', '..'] : ['.', '..', 'twig'], scandir(\dirname($this->twigCacheDir)));
    }

    public function testResetTwigCacheWithoutCacheDirectory(): void
    {
        $request = new Request();
        $request->headers->set('sw-twig-hmr-reset', '1');
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        (new TwigHmrCacheSubscriber(true, $this->twigCacheDir))->resetTwigCache($event);

        static::assertFalse((new Filesystem())->exists($this->twigCacheDir));
    }

    /**
     * @return iterable<string, array{bool, array<string, string>, int, bool}>
     */
    public static function provideResetRequests(): iterable
    {
        yield 'reset request in debug mode' => [true, ['sw-twig-hmr-reset' => '1'], HttpKernelInterface::MAIN_REQUEST, true];
        yield 'regular request in debug mode' => [true, [], HttpKernelInterface::MAIN_REQUEST, false];
        yield 'hot reload request without reset in debug mode' => [true, ['sw-twig-hmr' => '1'], HttpKernelInterface::MAIN_REQUEST, false];
        yield 'reset request without debug mode' => [false, ['sw-twig-hmr-reset' => '1'], HttpKernelInterface::MAIN_REQUEST, false];
        yield 'reset header on a sub request' => [true, ['sw-twig-hmr-reset' => '1'], HttpKernelInterface::SUB_REQUEST, false];
    }
}
