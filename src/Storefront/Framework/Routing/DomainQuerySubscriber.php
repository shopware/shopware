<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDomain\SalesChannelDomainQueryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class DomainQuerySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SalesChannelDomainQueryEvent::class => 'onSalesChannelDomainQuery',
        ];
    }

    public function onSalesChannelDomainQuery(SalesChannelDomainQueryEvent $event): void
    {
        $query = $event->getQueryBuilder();

        $query->addSelect(
            'LOWER(HEX(theme.id)) themeId',
            'theme.technical_name as themeName',
            'parentTheme.technical_name as parentThemeName',
        );

        $query->leftJoin('sales_channel', 'theme_sales_channel', 'theme_sales_channel', 'sales_channel.id = theme_sales_channel.sales_channel_id');
        $query->leftJoin('theme_sales_channel', 'theme', 'theme', 'theme_sales_channel.theme_id = theme.id');
        $query->leftJoin('theme', 'theme', 'parentTheme', 'theme.parent_theme_id = parentTheme.id');
    }
}
