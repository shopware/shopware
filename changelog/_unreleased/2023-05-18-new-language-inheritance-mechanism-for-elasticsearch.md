---
title: New language inheritance mechanism for elasticsearch
issue: NEXT-25613
flag: ES_MULTILINGUAL_INDEX
---
# Core 
* Added a new feature flag `ES_MULTILINGUAL_INDEX` in `Core/Framework/Resources/config/packages/feature.yaml`
___
# Elasticsearch
* Added a new script in `Framework/Indexing/Scripts/translated_field_sorting.groovy` to allow sorting by multiple language in painless script
* Changed `\Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser::parseSorting` to apply script sorting for translated fields
* Added class `\Shopware\Elasticsearch\Framework\Indexing\MultilingualEsIndexer` to allow indexing translatable entities in one index
* Deprecated class `\Shopware\Elasticsearch\Framework\Indexing\MultilingualEsIndexer`
* Deprecated class `\Shopware\Elasticsearch\Product\EsProductDefinition`
* Changed public const variables from `ElasticsearchProductDefinition` to `AbstractElasticsearchDefinition` 
* Deprecated these methods in `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset` due to unused:
    * `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset::setNextLanguage`
    * `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset::selectNextLanguage`
    * `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset::hasNextLanguage`
    * `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset::getLanguageId`
    * `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset::getLanguages`
* Deprecated `\Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition::buildTermQuery` as it will become abstract method from next major
* Changed method `\Shopware\Elasticsearch\Framework\ElasticsearchHelper::getIndexName` to not use `languageId` parameter to build index name
* Added a new subscriber method `\Shopware\Elasticsearch\Product\LanguageSubscriber::onLanguageWritten` to update mapping of index if there's new language created
* Deprecated subscriber method `\Shopware\Elasticsearch\Product\LanguageSubscriber::onSalesChannelWritten` as we are no longer need to add new index when a new sales channel language is created
* Changed method `\Shopware\Elasticsearch\Product\ProductSearchQueryBuilder::build` to apply new search queries when working with new index strategy (multilingual fields instead of multilingual indexes)
* Added a new DTO class `\Shopware\Elasticsearch\Product\SearchFieldConfig` that represent a search config of a searchable field
* Changed method `getMapping` in `\Shopware\Elasticsearch\Product\EsProductDefinition` to add language analyzer
___
# Upgrade Information

## Old data mapping structure is deprecated, introduce new data mapping structure:

* For the full reference, please read the [adr](../../adr/2023-04-11-new-language-inheritance-mechanism-for-opensearch.md)
* If you've defined your own Elasticsearch definitions, please prepare for the new structure by update your definition's `getMapping` and `fetch` methods:

```php
<?php

use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Core\Framework\Context;

class YourElasticsearchDefinition extends AbstractElasticsearchDefinition
{
    public function getMapping(Context $context): array
    {
        $languageIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`id`)) FROM language');
        // Fetch all language in system, for eg: ['2fbb5fe2e29a4d70aa5854ce7ce3e20b', '46986b26eadf4bb3929e9fc91821e294] for English and German language
        // These two language ids will be used in the translated fields mapping below

        $mapping = [
            // Non-translated fields are updated as current
            'productNumber' => [
                'type' => 'keyword',
                'normalizer' => 'sw_lowercase_normalizer',
                'fields' => [
                    'search' => [
                        'type' => 'text',
                    ],
                    'ngram' => [
                        'type' => 'text',
                        'analyzer' => 'sw_ngram_analyzer',
                    ],
                ],
            ],
            // Translated text fields mapping need to be updated with the new structure
            'name' =>[
                'properties' => [
                    '2fbb5fe2e29a4d70aa5854ce7ce3e20b' => [
                        'type' => 'keyword',
                        'normalizer' => 'sw_lowercase_normalizer',
                        'fields' => [
                            'search' => [
                                'type' => 'text',
                            ],
                            'ngram' => [
                                'type' => 'text',
                                'analyzer' => 'sw_ngram_analyzer',
                            ],
                        ],
                    ],
                    '46986b26eadf4bb3929e9fc91821e294' => [
                        'type' => 'keyword',
                        'normalizer' => 'sw_lowercase_normalizer',
                        'fields' => [
                            'search' => [
                                'type' => 'text',
                            ],
                            'ngram' => [
                                'type' => 'text',
                                'analyzer' => 'sw_ngram_analyzer',
                            ],
                        ]
                    ]
                ]
            ],
            // The same applied for customFields in case your entity's custom fields is translatable
            'customFields' =>[
                'properties' => [
                    '2fbb5fe2e29a4d70aa5854ce7ce3e20b' => [
                        'type' => 'object',
                        'dynamic' => true,
                        'properties' => [],
                    ],
                    '46986b26eadf4bb3929e9fc91821e294' => [
                        'type' => 'object',
                        'dynamic' => true,
                        'properties' => [],
                    ]
                ]
            ],
            // nested translated fields needs to be updated too in the same manner
            'manufacturer' => [
                'type' => 'nested',
                'properties' => [
                    'id' => [
                        'type' => 'keyword',
                        'normalizer' => 'sw_lowercase_normalizer',
                    ],
                    '_count' => [
                        'type' => 'long',
                    ],
                    'name' => [
                        'properties' => [
                            '2fbb5fe2e29a4d70aa5854ce7ce3e20b' => [
                                'type' => 'keyword',
                                'normalizer' => 'sw_lowercase_normalizer',
                                'fields' => [
                                    'search' => [
                                        'type' => 'text',
                                    ],
                                    'ngram' => [
                                        'type' => 'text',
                                        'analyzer' => 'sw_ngram_analyzer',
                                    ],
                                ]
                            ],
                            '46986b26eadf4bb3929e9fc91821e294' => [
                                'type' => 'keyword',
                                'normalizer' => 'sw_lowercase_normalizer',
                                'fields' => [
                                    'search' => [
                                        'type' => 'text',
                                    ],
                                    'ngram' => [
                                        'type' => 'text',
                                        'analyzer' => 'sw_ngram_analyzer',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];


        return $mapping;
    }

    public function fetch(array $ids, Context $context): array
    {
        // We need to fetch all available content of translated fields in all languages
        ...;

        return [
            '466f4eadf13a4486b851e747f5d99a4f' => [
                'name' => [
                    '2fbb5fe2e29a4d70aa5854ce7ce3e20b' => 'English foo',
                    '46986b26eadf4bb3929e9fc91821e294' => 'German foo',
                ],
                'manufacturer' => [
                    'id' => '5bf0d9be43cb41ccbb5781cec3052d91',
                    '_count' => 1,
                    'name' => [
                        '2fbb5fe2e29a4d70aa5854ce7ce3e20b' => 'English baz',
                        '46986b26eadf4bb3929e9fc91821e294' => 'German baz',
                    ],
                ],
                'productNumber' => 'PRODUCT_NUM',
            ],
        ];
    }
}
```

* The new structure will be applied since next major, however you can try it out by enabling the flag `ES_MULTILINGUAL_INDEX=1`

## Update your live shops

* After the flag is activated, you must run `bin/console es:index`, then new index mapping will be ready after the es indexing process is finished
___
# Next Major Version Changes

* In the previous implementation, each system language has its own index, but with the new implementation, every languages share the same index. This leads to the following changes in the next major: 
  * Old ES indexes is deprecated and will be removed since the next major. 
  * If you have custom elasticsearch definitions, you also need to write your own SearchQueryBuilder (Reference: \Shopware\Elasticsearch\Product\EsProductDefinition::buildTermQuery)
