<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

/**
 * @package storefront
 *
 * @phpstan-type Domain = array{url: string, id: string, salesChannelId: string, typeId: string, snippetSetId: string, currencyId: string, languageId: string, themeId: string, maintenance: string, maintenanceIpWhitelist: string, locale: string, themeHash: ?string, themeName: string, parentThemeName: string}
 */
abstract class AbstractDomainLoader
{
    abstract public function getDecorated(): AbstractDomainLoader;

    /**
     * @return array<string, Domain>
     */
    abstract public function load(): array;
}
