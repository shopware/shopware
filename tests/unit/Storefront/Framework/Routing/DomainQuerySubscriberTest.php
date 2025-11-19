<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\System\SalesChannel\SalesChannelDomain\SalesChannelDomainQueryEvent;
use Shopware\Core\Test\Stub\Doctrine\FakeConnection;
use Shopware\Storefront\Framework\Routing\DomainQuerySubscriber;

/**
 * @internal
 */
#[CoversClass(DomainQuerySubscriber::class)]
class DomainQuerySubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        $events = DomainQuerySubscriber::getSubscribedEvents();

        static::assertArrayHasKey(SalesChannelDomainQueryEvent::class, $events);
        static::assertSame('onSalesChannelDomainQuery', $events[SalesChannelDomainQueryEvent::class]);
    }

    public function testQueryIsExtendedWithThemeInformation(): void
    {
        $queryBuilder = new QueryBuilder(new FakeConnection([]));

        $queryBuilder->from('sales_channel', 'sales_channel');

        $event = new SalesChannelDomainQueryEvent($queryBuilder);
        $subscriber = new DomainQuerySubscriber();

        $subscriber->onSalesChannelDomainQuery($event);

        static::assertSame([
            'LOWER(HEX(theme.id)) themeId',
            'theme.technical_name as themeName',
            'parentTheme.technical_name as parentThemeName',
        ], $queryBuilder->getSelectParts());
        static::assertSame('SELECT LOWER(HEX(theme.id)) themeId, theme.technical_name as themeName, parentTheme.technical_name as parentThemeName FROM sales_channel LEFT JOIN theme_sales_channel theme_sales_channel ON sales_channel.id = theme_sales_channel.sales_channel_id LEFT JOIN theme theme ON theme_sales_channel.theme_id = theme.id LEFT JOIN theme parentTheme ON theme.parent_theme_id = parentTheme.id', $queryBuilder->getSQL());
    }
}
