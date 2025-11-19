<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use Shopware\Core\System\SalesChannel\SalesChannelDomain\AbstractDomainLoader instead.
 *
 * @phpstan-type Domain = array{url: string, id: string, salesChannelId: string, typeId: string, snippetSetId: string, currencyId: string, languageId: string, themeId?: string, maintenance: string, maintenanceIpWhitelist: string, locale: string, themeName?: string, parentThemeName?: string}
 */
#[Package('framework')]
abstract class AbstractDomainLoader
{
    abstract public function getDecorated(): AbstractDomainLoader;

    /**
     * @return array<string, Domain>
     */
    abstract public function load(): array;
}
