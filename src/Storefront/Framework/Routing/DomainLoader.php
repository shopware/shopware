<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelDomain\AbstractDomainLoader as CoreDomainLoader;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use Shopware\Core\System\SalesChannel\SalesChannelDomain\DomainLoader instead.
 *
 * @phpstan-import-type Domain from AbstractDomainLoader
 */
#[Package('framework')]
class DomainLoader extends AbstractDomainLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CoreDomainLoader $newImplementation
    ) {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use Shopware\Core\System\SalesChannel\SalesChannelDomain\DomainLoader instead.
     */
    public function getDecorated(): AbstractDomainLoader
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            'Shopware\Storefront\Framework\Routing\DomainLoader is deprecated and will be removed in v6.8.0.0. Use Shopware\Core\System\SalesChannel\SalesChannelDomain\DomainLoader instead.'
        );

        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<string, Domain>
     *
     * @deprecated tag:v6.8.0 - Will be removed, use Shopware\Core\System\SalesChannel\SalesChannelDomain\DomainLoader::load instead.
     */
    public function load(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            'Shopware\Storefront\Framework\Routing\DomainLoader::load is deprecated and will be removed in v6.8.0.0. Use Shopware\Core\System\SalesChannel\SalesChannelDomain\DomainLoader::load instead.'
        );

        return $this->newImplementation->load();
    }
}
