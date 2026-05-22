<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Catalog;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\AbstractUcpCapability;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[Package('framework')]
class CatalogSearchCapability extends AbstractUcpCapability
{
    public const NAME = 'dev.ucp.shopping.catalog.search';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSpecUrl(): string
    {
        return 'https://ucp.dev/specification/catalog/search/';
    }

    public function getSchemaUrl(): string
    {
        return 'https://ucp.dev/' . $this->getVersion() . '/schemas/shopping/catalog_search.json';
    }
}
