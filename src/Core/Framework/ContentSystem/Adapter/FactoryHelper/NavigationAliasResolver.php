<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves navigation aliases to category IDs from sales channel configuration.
 *
 * Aliases:
 * - main-navigation → navigationCategoryId
 * - service-navigation → serviceCategoryId
 * - footer-navigation → footerCategoryId
 *
 * If the alias is not recognized, it's returned unchanged (assumed to be a UUID).
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class NavigationAliasResolver
{
    private const ALIAS_MAIN_NAVIGATION = 'main-navigation';
    private const ALIAS_SERVICE_NAVIGATION = 'service-navigation';
    private const ALIAS_FOOTER_NAVIGATION = 'footer-navigation';

    public function resolve(string $alias, SalesChannelContext $context): string
    {
        $salesChannel = $context->getSalesChannel();

        return match ($alias) {
            self::ALIAS_MAIN_NAVIGATION => $salesChannel->getNavigationCategoryId(),
            self::ALIAS_SERVICE_NAVIGATION => $salesChannel->getServiceCategoryId() ?? $alias,
            self::ALIAS_FOOTER_NAVIGATION => $salesChannel->getFooterCategoryId() ?? $alias,
            default => $alias,
        };
    }
}
