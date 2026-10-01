<?php declare(strict_types=1);

namespace Shopware\Elasticsearch;

use OpenSearchDSL\BuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Product\SearchFieldConfig;

#[Package('inventory')]
abstract class AbstractTokenQueryBuilder
{
    abstract public function getDecorated(): self;

    /**
     * @param SearchFieldConfig[] $configs
     */
    abstract public function build(string $entity, string $token, array $configs, Context $context): ?BuilderInterface;
}
