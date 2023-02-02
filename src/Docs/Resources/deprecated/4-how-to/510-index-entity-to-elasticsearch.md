[titleEn]: <>(Index entities to elasticsearch)
[hash]: <>(article:how_to_index_es)

Once you have implemented an entity in the system that has several thousand records in the database, it makes sense to create compatibility with Elasticsearch.
This requires the `shopware/elasticsearch` bundle. If this is not available in your project, you can simply add it via `composer require shopware/elasticsearch`.

## Register the entity
To synchronize an entity to Elasticsearch the class `\Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition` is used.
The following shows how to implement such a synchronization for products.

```
<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\EntityMapper;

class ElasticsearchProductDefinition extends AbstractElasticsearchDefinition
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
}
```

You can then register the definition via the service tag `shopware.es.definition`:
```
<service id="Shopware\Elasticsearch\Product\ElasticsearchProductDefinition">
    <argument id="Shopware\Core\Content\Product\ProductDefinition" type="service"/>
    <argument id="Shopware\Elasticsearch\Framework\Indexing\EntityMapper" type="service"/>
    <tag name="shopware.es.definition" />
</service>
```

## Extending indexed data
By default, only the data of an entity that is directly stored in the entity is indexed. That means associations of your entity are not indexed.
However, if they are often used in searches, it makes sense to index them selectively. 
There are two things to consider here:
1. each indexed field needs a mapping
2. the data to be indexed have to be selected as well

For this you can use the functions `getMapping`, `extendCriteria` and `extendEntities` which you can simply overwrite:

```
<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\EntityMapper;

class ElasticsearchProductDefinition extends AbstractElasticsearchDefinition
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
                    'categoriesRo' => $this->mapper->mapField($definition, $definition->getField('categoriesRo'), $context),
                ]
            ),
        ];
    }

    public function extendCriteria(Criteria $criteria): void
    {
        $criteria->addAssociation('categoriesRo');
    }

    public function extendEntities(EntityCollection $collection): EntityCollection
    {
        /** @var Entity $element */
        foreach ($collection->getElements() as $entity) {
            $element->addExtension('foo', new FooStruct());
        }
        
        return $collection;
    }
}
```
The `categoriesRo` field can now be referenced in the search to filter products by category.
If a field is referenced in a search query, which is not indexed, the SQL search will be executed automatically.

The `FooStruct` will be indexed as well and need a mapping for the field which can be set via `getMapping`. 

## Fulltext search
By default, all fields of your entity are indexed as [`keyword` fields](https://www.elastic.co/guide/en/elasticsearch/reference/current/keyword.html). These fields cannot be used in the context of a full text search.
Instead of storing several [text fields](https://www.elastic.co/guide/en/elasticsearch/reference/current/text.html) yourself and trying to index the data differently, the core creates two fields for a full text search:
- `fullText` You should fill this field with all keywords that are relevant for the entity.
- `fullTextBoosted` You should fill this field with all very relevant keywords

You can optionally configure these fields using the `buildFullText` function. If you do not overwrite this function, the core simply uses all `\Shopware\Core\Framework\DataAbstractionLayer\Field\StringField` from your `EntityDefinition`. 
