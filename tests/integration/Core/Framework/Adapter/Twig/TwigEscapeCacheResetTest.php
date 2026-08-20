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
 * Drives the real `services_resetter` (not `reset()` directly), so it fails unless the reset actually runs.
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
            // Render so ServicesResetter has an initialized `twig` service to reset.
            $twig = $container->get('twig');
            static::assertInstanceOf(Environment::class, $twig);
            $twig->createTemplate('{{ "warmup"|escape }}')->render([]);
            static::assertTrue($container->initialized('twig'));

            // Warm the cache: a second identical escape is a hit, so the inner escaper runs only once.
            $callCount = 0;
            $escaper = new EscaperRuntime();
            $escaper->setEscaper('test', static function (string $value) use (&$callCount): string {
                ++$callCount;

                return $value;
            });
            CachedEscaperRuntime::escape($escaper, 'foo', 'test');
            CachedEscaperRuntime::escape($escaper, 'foo', 'test');
            $callsWhileWarm = $callCount;

            // What a worker fires between requests.
            $resetter = $container->get('services_resetter');
            static::assertInstanceOf(ServicesResetter::class, $resetter);
            $resetter->reset();

            // Cache cleared, so this is a miss and the inner escaper runs again.
            CachedEscaperRuntime::escape($escaper, 'foo', 'test');

            static::assertSame(1, $callsWhileWarm);
            static::assertSame(2, $callCount, 'ServicesResetter must clear the escape cache, else it grows unbounded in worker mode');
        } finally {
            CachedEscaperRuntime::resetEscapeCache();
        }
    }
}
