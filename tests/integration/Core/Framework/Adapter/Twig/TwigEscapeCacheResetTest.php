<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Twig\Environment;
use Twig\Runtime\EscaperRuntime;

/**
 * Regression guard for the escape-cache memory leak in long-running workers (issue #19272).
 *
 * The bug was not that the reset logic was wrong, but that Symfony's ServicesResetter never ran it:
 * the reset-carrying service was never initialized, so `initialized(...)` stayed false and the reset
 * was skipped. A unit test that calls `reset()` directly cannot catch that, because it bypasses the
 * one broken link. This test drives the real `services_resetter` from a booted container, exactly as
 * a worker runtime does between requests, so it fails on the unfixed code and passes once the reset
 * hangs off the always-initialized `twig` service.
 *
 * @internal
 */
#[Package('framework')]
class TwigEscapeCacheResetTest extends TestCase
{
    use KernelTestBehaviour;

    public function testServicesResetterClearsEscapeCacheBetweenRequests(): void
    {
        $container = $this->getContainer();

        CachedEscaperRuntime::resetEscapeCache();

        try {
            // Render once so the `twig` service is initialized. The fix hangs the reset off `twig`,
            // and ServicesResetter only resets services that a request already initialized.
            $twig = $container->get('twig');
            static::assertInstanceOf(Environment::class, $twig);
            $twig->createTemplate('{{ "warmup"|escape }}')->render([]);
            static::assertTrue($container->initialized('twig'), 'a render must initialize the twig service');

            // Warm the static cache under an isolated escaper strategy, and prove it really is a cache:
            // the inner escaper runs once, the second identical escape is served from the cache.
            $callCount = 0;
            $escaper = new EscaperRuntime();
            $escaper->setEscaper('test', static function (string $value) use (&$callCount): string {
                ++$callCount;

                return $value;
            });
            CachedEscaperRuntime::escape($escaper, 'foo', 'test');
            CachedEscaperRuntime::escape($escaper, 'foo', 'test');
            $callsWhileWarm = $callCount;

            // The exact reset a long-running runtime (RoadRunner, FrankenPHP, Swoole) fires between requests.
            $resetter = $container->get('services_resetter');
            static::assertInstanceOf(ServicesResetter::class, $resetter);
            $resetter->reset();

            // If the reset actually ran, the cache is empty and the next identical escape is a miss.
            // On the unfixed code the cache survives the reset and the inner escaper is not called again.
            CachedEscaperRuntime::escape($escaper, 'foo', 'test');

            static::assertSame(1, $callsWhileWarm, 'the escape cache should be warm before the reset (one inner call for two identical escapes)');
            static::assertSame(
                2,
                $callCount,
                'ServicesResetter must clear the escape cache between requests, otherwise it grows unbounded in worker mode'
            );
        } finally {
            CachedEscaperRuntime::resetEscapeCache();
        }
    }
}
