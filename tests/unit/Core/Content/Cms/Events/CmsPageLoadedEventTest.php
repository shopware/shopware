<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
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
}
