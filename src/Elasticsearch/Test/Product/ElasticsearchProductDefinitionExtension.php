<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\EntityMapper;

class ElasticsearchProductDefinitionExtension extends AbstractElasticsearchDefinition
{
    /**
     * @var ProductDefinition
     */
    private $definition;

    public function __construct(ProductDefinition $definition, EntityMapper $mapper)
    {
        parent::__construct($mapper);
        $this->definition = $definition;
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getMapping(Context $context): array
    {
        $definition = $this->definition;

        return [
            '_source' => ['includes' => ['id']],
            'properties' => array_merge(
                $this->mapper->mapFields($definition, $context),
                [
                    'toOne' => $this->mapper->mapField($definition, $definition->getField('toOne'), $context),
                    'categoriesRo' => $this->mapper->mapField($definition, $definition->getField('categoriesRo'), $context),
                    'properties' => $this->mapper->mapField($definition, $definition->getField('properties'), $context),
                    'manufacturer' => $this->mapper->mapField($definition, $definition->getField('manufacturer'), $context),
                    'tags' => $this->mapper->mapField($definition, $definition->getField('tags'), $context),
                    'options' => $this->mapper->mapField($definition, $definition->getField('options'), $context),
                    'visibilities' => $this->mapper->mapField($definition, $definition->getField('visibilities'), $context),
                    'configuratorSettings' => $this->mapper->mapField($definition, $definition->getField('configuratorSettings'), $context),
                ]
            ),
        ];
    }

    public function extendCriteria(Criteria $criteria): void
    {
        $criteria
            ->addAssociation('toOne')
            ->addAssociation('categoriesRo')
            ->addAssociation('properties')
            ->addAssociation('manufacturer')
            ->addAssociation('tags')
            ->addAssociation('configuratorSettings')
            ->addAssociation('options')
            ->addAssociation('visibilities')
        ;
    }
}
