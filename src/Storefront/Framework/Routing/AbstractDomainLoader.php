<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesAbstract;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Routing\Struct\DomainCollection;

/**
 * @phpstan-type Domain = array{url: string, id: string, salesChannelId: string, typeId: string, snippetSetId: string, currencyId: string, languageId: string, themeId: string, maintenance: string, maintenanceIpAllowlist: string, locale: string, themeName: string, parentThemeName: string}
 */
#[Package('discovery')]
abstract class AbstractDomainLoader
{
    abstract public function getDecorated(): AbstractDomainLoader;

    /**
     * @deprecated tag:v6.8.0 - reason:becomes-unused - Will be removed, use loadDomains() instead
     *
     * @return array<string, Domain>
     */
    abstract public function load(): array;

    #[BecomesAbstract(version: 'v6.8.0', description: 'The default implementation that builds the collection from the deprecated load() will be removed.')]
    public function loadDomains(): DomainCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf('Relying on the default implementation of %s::loadDomains() is deprecated. Implement loadDomains() in %s.', self::class, static::class)
        );

        return DomainCollection::fromArray($this->load());
    }
}
