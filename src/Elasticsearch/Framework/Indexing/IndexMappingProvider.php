<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;

#[Package('framework')]
class IndexMappingProvider
{
    /**
     * @internal
     *
     * @param list<mixed> $mapping
     */
    public function __construct(
        private readonly array $mapping,
    ) {
    }

    /**
     * @return list<mixed>
     */
    public function build(AbstractElasticsearchDefinition $definition, Context $context): array
    {
        $mapping = $definition->getMapping($context);

        return array_merge_recursive($mapping, $this->mapping);
    }
}
