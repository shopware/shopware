<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\System\SalesChannel\SalesChannelAnalyticsLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelAnalyticsLoader::class)]
class SalesChannelAnalyticsLoaderTest extends TestCase
{
    public function testSalesChannelDoesNotHaveAnalytics(): void
    {
        $event = $this->getEvent(Generator::generateSalesChannelContext());
        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> */
        $repository = new StaticEntityRepository([]);

        $loader = new SalesChannelAnalyticsLoader($repository);
        $loader->loadAnalytics($event);

        static::assertArrayNotHasKey('storefrontAnalytics', $event->getParameters());
    }

    public function testSalesChannelHasAnalytics(): void
    {
        $analyticsId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId($analyticsId);
        $event = $this->getEvent($salesChannelContext);
        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setId($analyticsId);
        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([$analytics])]);

        $loader = new SalesChannelAnalyticsLoader($repository);
        $loader->loadAnalytics($event);

        static::assertArrayHasKey('storefrontAnalytics', $event->getParameters());
        static::assertInstanceOf(SalesChannelAnalyticsEntity::class, $event->getParameters()['storefrontAnalytics']);
    }

    public function testSalesChannelAnalyticsNotFound(): void
    {
        $analyticsId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId($analyticsId);
        $event = $this->getEvent($salesChannelContext);
        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);

        $loader = new SalesChannelAnalyticsLoader($repository);
        $loader->loadAnalytics($event);

        static::assertArrayHasKey('storefrontAnalytics', $event->getParameters());
        static::assertNull($event->getParameters()['storefrontAnalytics']);
    }

    public function testIsNotActiveWithoutAnalytics(): void
    {
        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> */
        $repository = new StaticEntityRepository([]);

        $loader = new SalesChannelAnalyticsLoader($repository);

        // the static repository fails on a search it has no result for, so no query runs
        static::assertFalse($loader->isActive(Generator::generateSalesChannelContext()));
    }

    public function testIsActiveWithActiveAnalytics(): void
    {
        static::assertTrue($this->createLoader(true)->isActive($this->contextWithAnalytics()));
    }

    public function testIsNotActiveWithInactiveAnalytics(): void
    {
        static::assertFalse($this->createLoader(false)->isActive($this->contextWithAnalytics()));
    }

    public function testIsNotActiveWhenTheAnalyticsAreNotFound(): void
    {
        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);

        static::assertFalse((new SalesChannelAnalyticsLoader($repository))->isActive($this->contextWithAnalytics()));
    }

    private function createLoader(bool $active): SalesChannelAnalyticsLoader
    {
        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setId(Uuid::randomHex());
        $analytics->setActive($active);

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([$analytics])]);

        return new SalesChannelAnalyticsLoader($repository);
    }

    private function contextWithAnalytics(): SalesChannelContext
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId(Uuid::randomHex());

        return $salesChannelContext;
    }

    private function getEvent(SalesChannelContext $salesChannelContext): StorefrontRenderEvent
    {
        return new StorefrontRenderEvent(
            'test.html.twig',
            [],
            new Request(),
            $salesChannelContext,
        );
    }
}
