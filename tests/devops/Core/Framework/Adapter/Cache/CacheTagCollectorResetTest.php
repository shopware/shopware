<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

/**
 * The collector keeps the HTTP cache tags per request URI. Long running runtimes handle many requests with one
 * container, so it has to be reset between requests like every other `kernel.reset` service, otherwise the tags of
 * all previous requests pile up for the whole lifetime of a worker.
 *
 * Drives the real `services_resetter`, so it fails unless the `kernel.reset` tag is actually wired.
 *
 * @internal
 */
#[Package('framework')]
class CacheTagCollectorResetTest extends TestCase
{
    use KernelTestBehaviour;

    public function testServicesResetterDropsCollectedTags(): void
    {
        $collector = static::getContainer()->get(CacheTagCollector::class);

        $requestStack = static::getContainer()->get('request_stack');
        static::assertInstanceOf(RequestStack::class, $requestStack);

        $request = Request::create('/some-page');
        $requestStack->push($request);

        try {
            $collector->addTag('tag-a');
            static::assertSame(['tag-a'], $collector->get($request));

            $resetter = static::getContainer()->get('services_resetter');
            static::assertInstanceOf(ServicesResetter::class, $resetter);
            $resetter->reset();

            static::assertSame([], $collector->get($request));
        } finally {
            $requestStack->pop();
        }
    }
}
