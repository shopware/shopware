<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

/**
 * The collector keeps the HTTP cache tags per request URI. Long running runtimes handle many requests with one
 * container, so it has to be reset between requests like every other `kernel.reset` service, otherwise the tags of
 * all previous requests pile up for the whole lifetime of a worker.
 *
 * Uses its own kernel, resetting the services of the shared test kernel would affect other tests.
 *
 * @internal
 */
#[Package('framework')]
class CacheTagCollectorResetTest extends TestCase
{
    public function testServicesResetterDropsCollectedTags(): void
    {
        $kernel = KernelLifecycleManager::createKernel();
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            static::assertInstanceOf(ContainerInterface::class, $container);

            $collector = $container->get(CacheTagCollector::class);
            static::assertInstanceOf(CacheTagCollector::class, $collector);

            $requestStack = $container->get('request_stack');
            static::assertInstanceOf(RequestStack::class, $requestStack);

            $request = Request::create('/some-page');
            $requestStack->push($request);

            try {
                $collector->addTag('tag-a');
                static::assertSame(['tag-a'], $collector->get($request));

                $resetter = $container->get('services_resetter');
                static::assertInstanceOf(ServicesResetter::class, $resetter);
                $resetter->reset();

                static::assertSame([], $collector->get($request));
            } finally {
                $requestStack->pop();
            }
        } finally {
            $kernel->shutdown();
        }
    }
}
