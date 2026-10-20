<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsPageLoadedEvent::class)]
class CmsPageLoadedEventTest extends TestCase
{
    public function testAcceptsACmsPageCollection(): void
    {
        $result = new CmsPageCollection();

        $event = new CmsPageLoadedEvent(new Request(), $result, static::createStub(SalesChannelContext::class));

        static::assertSame($result, $event->getResult());
    }

    public function testRejectsAPlainEntityCollection(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Passing a plain EntityCollection as $result is deprecated, pass a CmsPageCollection instead.'
        ));
        // @phpstan-ignore argument.type (the deprecated wide parameter type is exactly what this test exercises)
        new CmsPageLoadedEvent(new Request(), new EntityCollection(), static::createStub(SalesChannelContext::class));
    }

    public function testExposesRequestAndSalesChannelContext(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);
        $request = new Request();

        $event = new CmsPageLoadedEvent($request, new CmsPageCollection(), $salesChannelContext);

        static::assertSame($request, $event->getRequest());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($context, $event->getContext());
    }
}
