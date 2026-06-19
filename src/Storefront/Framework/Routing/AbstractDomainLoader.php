<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Routing\Struct\DomainCollection;
use Shopware\Storefront\Framework\Routing\Struct\DomainStruct;

/**
 * @phpstan-type Domain = array{url: string, id: string, salesChannelId: string, typeId: string, snippetSetId: string, currencyId: string, languageId: string, themeId: string, maintenance: string, maintenanceIpAllowlist: string, locale: string, themeName: string, parentThemeName: string}
 */
#[Package('framework')]
abstract class AbstractDomainLoader
{
    abstract public function getDecorated(): AbstractDomainLoader;

    /**
     * @deprecated tag:v6.8.0 - reason:becomes-unused - Will be removed, use loadDomains() instead
     *
     * @return array<string, Domain>
     */
    abstract public function load(): array;

    /**
     * The default implementation builds the collection from the deprecated load() for backward compatibility.
     * It will be removed with v6.8 and this method becomes abstract; decorators should implement it directly.
     */
    public function loadDomains(): DomainCollection
    {
        $domains = new DomainCollection();

        foreach ($this->load() as $key => $domain) {
            $domains->set($key, DomainStruct::fromArray($domain));
        }

        return $domains;
    }
}
