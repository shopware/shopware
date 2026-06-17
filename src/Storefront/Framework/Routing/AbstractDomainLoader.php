<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type Domain = array{url: string, id: string, salesChannelId: string, typeId: string, snippetSetId: string, currencyId: string, languageId: string, themeId: string, maintenance: string, maintenanceIpAllowlist: string, locale: string, themeName: string, parentThemeName: string}
 */
#[Package('framework')]
abstract class AbstractDomainLoader
{
    abstract public function getDecorated(): AbstractDomainLoader;

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return array<string, \Shopware\Storefront\Framework\Routing\Struct\DomainStruct>
     *
     * @return array<string, Domain>
     */
    abstract public function load(): array;
}
