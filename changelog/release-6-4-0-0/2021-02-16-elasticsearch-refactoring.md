---
title: Elasticsearch refactoring
issue: NEXT-12159
---

# Administration

* Changed product stream condition service from exclude to include pattern
    * Replaced `isPropertyInBlacklist` with `isPropertyInAllowList`
    * Replaced `addToGeneralBlacklist` with `addToGeneralAllowList`
    * Replaced `addToEntityBlacklist` with `addToEntityAllowList`
    * Replaced `removeFromGeneralBlacklist` with `removeFromGeneralAllowList`
___

# Core

* Added new method `Shopware\Core\Content\Test\Product\ProductBuilder:translation`
* Added new method `Shopware\Core\Framework\Context:addContextState`
* Added new method `Shopware\Core\Framework\Context:hasContextState`
* Added new method `Shopware\Core\Framework\Context:removeContextState`
* Added new parameter `$limit` to method `Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory:createIterator`
* Added new parameter `$loggerLevel` to method `Shopware\Core\Framework\Log\LoggerFactory:createRotating`
___

# Elasticsearch

* Changed IndexCreator to create the alias directly after creation of index when not existing
* Added a own logger for Elasticsearch
  * The file is located at `var/log/elasticsearch.log`
  * Will be rotated every day
* Added new method `Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition:fetch`
* Added new method `Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition:stripText`
* Added new parameter `$logger` to method `Shopware\Elasticsearch\Framework\ClientFactory:createClient`
* Added new parameter `$debug` to method `Shopware\Elasticsearch\Framework\ClientFactory:createClient`
* Added new parameter `$alias` to method `Shopware\Elasticsearch\Framework\Indexing\IndexCreator:createIndex`
* Added new method `Shopware\Elasticsearch\Product\ElasticsearchProductDefinition:fetch`
* Removed parameter `$data` from method `Shopware\Elasticsearch\Test\Product\ElasticsearchProductTest:testStorefrontListing`
* Deprecated `AbstractElasticsearchDefinition::extendCriteria`, use `fetch` instead
* Deprecated `AbstractElasticsearchDefinition::extendEntities`, use `fetch` instead
* Changed `\Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer` to register it as own message handler and depend no longer on the entity indexer
* Removed method `\Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer:extendDocuments`
* Removed method `Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition:extendCriteria`
* Removed method `Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition:extendEntities`
* Removed parameter `$collection` from method `Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition:extendDocuments`
* Removed method `Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition:buildFullText`
* Removed method `Shopware\Elasticsearch\Product\ElasticsearchProductDefinition:extendCriteria`
* Removed parameter `$collection` from method `Shopware\Elasticsearch\Product\ElasticsearchProductDefinition:extendDocuments`
* Removed method `Shopware\Elasticsearch\Test\Product\ElasticsearchProductTest:testTermsAggregationWithLimit`
* Removed method `Shopware\Elasticsearch\Test\Product\ElasticsearchProductTest:testTermsAggregationWithSorting`
* Removed method `Shopware\Elasticsearch\Test\Product\ElasticsearchProductTest:testFilterAggregationWithTerms`
* Removed following class `Shopware\Elasticsearch\Framework\FullText`

___
# Upgrade Information

## Elasticsearch Refactoring

To improve the performance and reliability of Elasticsearch, we have decided to refactor Elasticsearch in the first iteration in the Storefront only for Product listing and Product searches.
This allows us to create a optimized Elasticsearch index with only required fields selected by an single sql to make the indexing fast as possible.
This also means for extensions, they need to extend the indexing to make their fields searchable.

Here is an simple decoration to add a new random field named `myNewField` to the index. 
For adding more information from the Database you should execute a single query with all document ids (`array_column($documents, 'id'')`) and map the values

```xml
<service id="MyDecorator" decorates="Shopware\Elasticsearch\Product\ElasticsearchProductDefinition">
    <argument type="service" id="MyDecorator.inner"/>
    <argument type="service" id="dbal_connection"/>
</service>
```

```php
<?php

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\EntityMapper;
use Doctrine\DBAL\Connection;

class MyDecorator extends AbstractElasticsearchDefinition
{
    private AbstractElasticsearchDefinition $productDefinition;
    private Connection $connection;

    public function __construct(AbstractElasticsearchDefinition $productDefinition, Connection $connection)
    {
        $this->productDefinition = $productDefinition;
        $this->connection = $connection;
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->productDefinition->getEntityDefinition();
    }

    public function getMapping(Context $context): array
    {
        $mapping = $this->productDefinition->getMapping($context);

        $mapping['properties']['myNewField'] = EntityMapper::INT_FIELD;

        // Adding nested field with id
        $mapping['properties']['myManyToManyAssociation'] = [
            'type' => 'nested',
            'properties' => [
                'id' => EntityMapper::KEYWORD_FIELD,
            ],
        ];

        return $mapping;
    }

    public function fetch(array $ids, Context $context): array
    {
        $documents = $this->productDefinition->fetch($ids, $context);

        $query = <<<'SQL'
SELECT LOWER(HEX(mytable.product_id)) as id, GROUP_CONCAT(LOWER(HEX(mytable.myFkField)) SEPARATOR "|") as relationIds
FROM mytable
WHERE
    mytable.product_id IN(:ids) AND
    mytable.product_version_id = :liveVersion
SQL;


        $associationData = $this->connection->fetchAllKeyValue(
            $query,
            [
                'ids' => $ids,
                'liveVersion' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)
            ],
            [
                'ids' => Connection::PARAM_STR_ARRAY
            ]
        );

        foreach ($documents as &$document) {
            // Normal field directly on the product
            $document['myNewField'] = random_int(PHP_INT_MIN, PHP_INT_MAX);

            // Nested object with an id field
            $document['myManyToManyAssociation'] = array_map(function (string $id) {
                return ['id' => $id];
            }, array_filter(explode('|', $associationData[$document['id']] ?? '')));
        }

        return $documents;
    }
}
```

When searching products make sure you add elasticsearch aware to your criteria to use Elasticsearch in background.

```php
$criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
$context = \Shopware\Core\Framework\Context::createDefaultContext();
// Enables elasticsearch for this search
$context->addState(\Shopware\Core\Framework\Context::STATE_ELASTICSEARCH_AWARE);

$repository->search($criteria, $context);
```
