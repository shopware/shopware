<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test\Product;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;

class ElasticsearchProductDefinitionExtension extends ElasticsearchProductDefinition
{
    public function getMapping(Context $context): array
    {
        $origin = parent::getMapping($context);

        $definition = $this->definition;

        $origin['properties']['toOne'] = $this->mapper->mapField($definition, $definition->getField('toOne'), $context);

        return $origin;
    }

    public function extendCriteria(Criteria $criteria): void
    {
        parent::extendCriteria($criteria);

        $criteria->addAssociation('toOne');
    }
}
