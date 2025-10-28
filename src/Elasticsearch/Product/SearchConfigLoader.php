<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader as CoreSearchConfigLoader;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
/**
 * @deprecated tag:v6.8.0 - will be removed, use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader instead
 */
class SearchConfigLoader extends CoreSearchConfigLoader
{
}
