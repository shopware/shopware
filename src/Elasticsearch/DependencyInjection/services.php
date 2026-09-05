<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\DependencyInjection;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\Language\LanguageLoader;
use Shopware\Core\System\Language\SalesChannelLanguageLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Elasticsearch\AbstractFieldQueryBuilder;
use Shopware\Elasticsearch\AbstractTokenQueryBuilder;
use Shopware\Elasticsearch\Admin\AdminCreateAliasTask;
use Shopware\Elasticsearch\Admin\AdminCreateAliasTaskHandler;
use Shopware\Elasticsearch\Admin\AdminElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Admin\AdminElasticsearchHelper;
use Shopware\Elasticsearch\Admin\AdminSearchController;
use Shopware\Elasticsearch\Admin\AdminSearcher;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Indexer\CategoryAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\CmsPageAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\CustomerAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\CustomerGroupAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\LandingPageAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\ManufacturerAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\MediaAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\NewsletterRecipientAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\OrderAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\PaymentMethodAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\ProductAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\ProductStreamAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\PromotionAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\PropertyGroupAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\SalesChannelAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Indexer\ShippingMethodAdminSearchIndexer;
use Shopware\Elasticsearch\Admin\Subscriber\RefreshIndexSubscriber;
use Shopware\Elasticsearch\ExplainFieldQueryBuilder;
use Shopware\Elasticsearch\FieldQueryBuilder;
use Shopware\Elasticsearch\Framework\ClientFactory;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchAdminIndexingCommand;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchAdminResetCommand;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchAdminStatusCommand;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchAdminTestCommand;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchAdminUpdateMappingCommand;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchCleanIndicesCommand;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchCreateAliasCommand;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchResetCommand;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchStatusCommand;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchTestAnalyzerCommand;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchUpdateMappingCommand;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchAggregationHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchSearchHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregatorHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearchHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchTokenizer;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldMapper;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Shopware\Elasticsearch\Framework\ElasticsearchLanguageProvider;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\ElasticsearchStagingHandler;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTask;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\IndexCreator;
use Shopware\Elasticsearch\Framework\Indexing\IndexManager;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingProvider;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Shopware\Elasticsearch\Framework\Subscriber\InvalidateExpiredCacheSubscriber;
use Shopware\Elasticsearch\Framework\SystemInstallListener;
use Shopware\Elasticsearch\Framework\SystemUpdateListener;
use Shopware\Elasticsearch\NestedFieldQueryBuilder;
use Shopware\Elasticsearch\Product\AbstractProductSearchQueryBuilder;
use Shopware\Elasticsearch\Product\CustomFieldSetGateway;
use Shopware\Elasticsearch\Product\CustomFieldUpdater;
use Shopware\Elasticsearch\Product\ElasticsearchCustomFieldsMappingHelper;
use Shopware\Elasticsearch\Product\ElasticsearchOptimizeSwitch;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Shopware\Elasticsearch\Product\LanguageSubscriber;
use Shopware\Elasticsearch\Product\ProductCriteriaParser;
use Shopware\Elasticsearch\Product\ProductCustomFieldsUsedUpdater;
use Shopware\Elasticsearch\Product\ProductSearchBuilder;
use Shopware\Elasticsearch\Product\ProductSearchQueryBuilder;
use Shopware\Elasticsearch\Product\ProductUpdater;
use Shopware\Elasticsearch\Product\SearchKeywordReplacement;
use Shopware\Elasticsearch\Product\StopwordTokenFilter;
use Shopware\Elasticsearch\Profiler\DataCollector;
use Shopware\Elasticsearch\TokenQueryBuilder;
use Shopware\Elasticsearch\TranslatedFieldQueryBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('elasticsearch.index.config', [
            'settings' => [
                'index' => '%elasticsearch.index_settings%',
                'analysis' => '%elasticsearch.analysis%',
            ],
        ])
        ->set('elasticsearch.index.mapping', [
            'dynamic_templates' => '%elasticsearch.dynamic_templates%',
        ])
        ->set('elasticsearch.administration.index.config', [
            'settings' => [
                'index' => '%elasticsearch.administration.index_settings%',
                'analysis' => '%elasticsearch.administration.analysis%',
            ],
        ])
        ->set('elasticsearch.administration.index.mapping', [
            'dynamic_templates' => '%elasticsearch.administration.dynamic_templates%',
        ]);

    $services = $containerConfigurator->services();

    $services->set(ElasticsearchTokenizer::class);

    $services->set(CriteriaParser::class)
        ->args([
            service(EntityDefinitionQueryHelper::class),
            service(CustomFieldService::class),
            service(AbstractKeyValueStorage::class),
        ]);

    $services->set(ElasticsearchHelper::class)
        ->public()
        ->args([
            param('kernel.environment'),
            param('elasticsearch.enabled'),
            param('elasticsearch.indexing_enabled'),
            param('elasticsearch.index_prefix'),
            param('elasticsearch.throw_exception'),
            service(Client::class),
            service(ElasticsearchRegistry::class),
            service(CriteriaParser::class),
            service('shopware.elasticsearch.logger'),
            service(SystemConfigService::class),
        ]);

    $services->set(ElasticsearchIndexingUtils::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
            service('parameter_bag'),
        ]);

    $services->set(ElasticsearchFieldBuilder::class)
        ->args([
            service(LanguageLoader::class),
            service(ElasticsearchIndexingUtils::class),
            param('elasticsearch.language_analyzer_mapping'),
        ]);

    $services->set(ElasticsearchFieldMapper::class)
        ->args([
            service(ElasticsearchIndexingUtils::class),
        ]);

    $services->set(Client::class)
        ->public()
        ->lazy()
        ->factory([ClientFactory::class, 'createClient'])
        ->args([
            param('elasticsearch.hosts'),
            service('shopware.elasticsearch.logger'),
            param('kernel.debug'),
            param('elasticsearch.ssl'),
        ]);

    $services->set('admin.openSearch.client', Client::class)
        ->public()
        ->lazy()
        ->factory([ClientFactory::class, 'createClient'])
        ->args([
            param('elasticsearch.administration.hosts'),
            service('shopware.elasticsearch.logger'),
            param('kernel.debug'),
            param('elasticsearch.ssl'),
        ]);

    $services->set(IndexCreator::class)
        ->args([
            service(Client::class),
            param('elasticsearch.index.config'),
            service(IndexMappingProvider::class),
            service('event_dispatcher'),
            service(ElasticsearchHelper::class),
            param('elasticsearch.dimension_normalize'),
        ]);

    $services->set(IndexManager::class)
        ->args([
            service(Client::class),
            service(ElasticsearchHelper::class),
            service(ElasticsearchRegistry::class),
        ]);

    $services->set(InvalidateExpiredCacheSubscriber::class)
        ->args([
            service(IndexManager::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(IndexMappingProvider::class)
        ->args([
            param('elasticsearch.index.mapping'),
        ]);

    $services->set(IndexMappingUpdater::class)
        ->args([
            service(ElasticsearchRegistry::class),
            service(ElasticsearchHelper::class),
            service(Client::class),
            service(IndexMappingProvider::class),
            service(AbstractKeyValueStorage::class),
        ]);

    $services->set(ElasticsearchIndexingCommand::class)
        ->args([
            service(ElasticsearchIndexer::class),
            service('messenger.default_bus'),
            service(CreateAliasTaskHandler::class),
            param('elasticsearch.indexing_enabled'),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchTestAnalyzerCommand::class)
        ->args([
            service(Client::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchStatusCommand::class)
        ->args([
            service(Client::class),
            service(Connection::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchResetCommand::class)
        ->args([
            service(Client::class),
            service(ElasticsearchOutdatedIndexDetector::class),
            service(Connection::class),
            service('shopware.increment.gateway.registry'),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchUpdateMappingCommand::class)
        ->args([
            service(IndexMappingUpdater::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchLanguageProvider::class)
        ->args([
            service('language.repository'),
            service('event_dispatcher'),
        ]);

    $services->set(ProductUpdater::class)
        ->args([
            service(ElasticsearchIndexer::class),
            service(ProductDefinition::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AbstractElasticsearchSearchHydrator::class, ElasticsearchEntitySearchHydrator::class);

    $services->set(AbstractElasticsearchAggregationHydrator::class, ElasticsearchEntityAggregatorHydrator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(ElasticsearchEntitySearcher::class)
        ->decorate(EntitySearcherInterface::class, null, 1000)
        ->public()
        ->args([
            service(Client::class),
            service(ElasticsearchEntitySearcher::class . '.inner'),
            service(ElasticsearchHelper::class),
            service(CriteriaParser::class),
            service(AbstractElasticsearchSearchHydrator::class),
            service('event_dispatcher'),
            param('elasticsearch.search.timeout'),
            param('elasticsearch.search.search_type'),
            param('elasticsearch.search.precision_threshold'),
        ]);

    $services->set(ElasticsearchEntityAggregator::class)
        ->decorate(EntityAggregatorInterface::class, null, 1000)
        ->public()
        ->args([
            service(ElasticsearchHelper::class),
            service(Client::class),
            service(ElasticsearchEntityAggregator::class . '.inner'),
            service(AbstractElasticsearchAggregationHydrator::class),
            service('event_dispatcher'),
            param('elasticsearch.search.timeout'),
            param('elasticsearch.search.search_type'),
        ]);

    $services->set(SearchKeywordReplacement::class)
        ->decorate(SearchKeywordUpdater::class, null, -50000)
        ->args([
            service(SearchKeywordReplacement::class . '.inner'),
            service(ElasticsearchHelper::class),
        ]);

    $services->set(ProductSearchBuilder::class)
        ->decorate(ProductSearchBuilderInterface::class, null, -50000)
        ->args([
            service(ProductSearchBuilder::class . '.inner'),
            service(ElasticsearchHelper::class),
            service(ProductDefinition::class),
            param('elasticsearch.search.term_max_length'),
        ]);

    $services->set(CreateAliasTaskHandler::class)
        ->public()
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Client::class),
            service(Connection::class),
            service(ElasticsearchHelper::class),
            param('elasticsearch.index.config'),
            service('event_dispatcher'),
        ])
        ->tag('messenger.message_handler');

    $services->set(CreateAliasTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(ElasticsearchRegistry::class)
        ->args([
            tagged_iterator('shopware.es.definition'),
        ]);

    $services->set(ElasticsearchStagingHandler::class)
        ->args([
            param('shopware.staging.elasticsearch.check_for_existence'),
            service(ElasticsearchHelper::class),
            service(ElasticsearchOutdatedIndexDetector::class),
        ]);

    $services->set(ElasticsearchProductDefinition::class)
        ->args([
            service(ProductDefinition::class),
            service(Connection::class),
            service(AbstractProductSearchQueryBuilder::class),
            service(ElasticsearchFieldBuilder::class),
            service(ElasticsearchFieldMapper::class),
            service(SalesChannelLanguageLoader::class),
            param('elasticsearch.product.exclude_source'),
            param('kernel.environment'),
            service(LanguageLoader::class),
        ])
        ->tag('shopware.es.definition');

    $services->set(StopwordTokenFilter::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(AbstractProductSearchQueryBuilder::class, ProductSearchQueryBuilder::class)
        ->args([
            service(ProductDefinition::class),
            service(StopwordTokenFilter::class),
            service(SearchConfigLoader::class),
            service(AbstractTokenQueryBuilder::class),
            service(ElasticsearchTokenizer::class),
        ]);

    $services->set(AbstractFieldQueryBuilder::class, FieldQueryBuilder::class)
        ->args([
            param('elasticsearch.analysis.filter.sw_ngram_filter.min_gram'),
            param('elasticsearch.use_language_analyzer'),
            param('elasticsearch.search.dismax_tie_breaker'),
            param('elasticsearch.search.boost.exact'),
            param('elasticsearch.search.boost.phrase'),
            param('elasticsearch.search.boost.fuzzy'),
            param('elasticsearch.search.boost.prefix'),
            param('elasticsearch.search.boost.partial'),
        ]);

    $services->set(TranslatedFieldQueryBuilder::class)
        ->decorate(AbstractFieldQueryBuilder::class, null, 300)
        ->args([
            service(TranslatedFieldQueryBuilder::class . '.inner'),
            service(AbstractKeyValueStorage::class),
            param('elasticsearch.search.dismax_tie_breaker'),
        ]);

    $services->set(NestedFieldQueryBuilder::class)
        ->decorate(AbstractFieldQueryBuilder::class, null, 200)
        ->args([
            service(NestedFieldQueryBuilder::class . '.inner'),
        ]);

    $services->set(ExplainFieldQueryBuilder::class)
        ->decorate(AbstractFieldQueryBuilder::class, null, 100)
        ->args([
            service(ExplainFieldQueryBuilder::class . '.inner'),
        ]);

    $services->set(AbstractTokenQueryBuilder::class, TokenQueryBuilder::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(CustomFieldService::class),
            service(AbstractFieldQueryBuilder::class),
        ]);

    $services->alias(TokenQueryBuilder::class, AbstractTokenQueryBuilder::class);

    $services->set(CustomFieldUpdater::class)
        ->args([
            service(ElasticsearchHelper::class),
            service(CustomFieldSetGateway::class),
            service(ElasticsearchCustomFieldsMappingHelper::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomFieldSetGateway::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(ElasticsearchCustomFieldsMappingHelper::class)
        ->args([
            service(ElasticsearchOutdatedIndexDetector::class),
            service(Client::class),
            service(CustomFieldSetGateway::class),
        ]);

    $services->set(ProductCustomFieldsUsedUpdater::class)
        ->args([
            service(ElasticsearchHelper::class),
            service(ElasticsearchCustomFieldsMappingHelper::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ElasticsearchCreateAliasCommand::class)
        ->args([
            service(CreateAliasTaskHandler::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchCleanIndicesCommand::class)
        ->args([
            service(Client::class),
            service(ElasticsearchOutdatedIndexDetector::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchAdminStatusCommand::class)
        ->args([
            service('admin.openSearch.client'),
            service(Connection::class),
            service(AdminElasticsearchHelper::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchAdminIndexingCommand::class)
        ->args([
            service(AdminSearchRegistry::class),
        ])
        ->tag('console.command')
        ->tag('kernel.event_subscriber');

    $services->set(ElasticsearchAdminResetCommand::class)
        ->args([
            service('admin.openSearch.client'),
            service(Connection::class),
            service('shopware.increment.gateway.registry'),
            service(AdminElasticsearchHelper::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchAdminTestCommand::class)
        ->args([
            service(AdminSearcher::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchAdminUpdateMappingCommand::class)
        ->args([
            service(AdminSearchRegistry::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchOutdatedIndexDetector::class)
        ->args([
            service(Client::class),
            service(ElasticsearchRegistry::class),
            service(ElasticsearchHelper::class),
        ]);

    $services->set(ElasticsearchIndexer::class)
        ->args([
            service(Connection::class),
            service(ElasticsearchHelper::class),
            service(ElasticsearchRegistry::class),
            service(IndexCreator::class),
            service(IteratorFactory::class),
            service(Client::class),
            service('shopware.elasticsearch.logger'),
            service('event_dispatcher'),
            param('elasticsearch.indexing_batch_size'),
            service(ClockInterface::class),
            param('elasticsearch.refresh_after_bulk'),
        ])
        ->tag('messenger.message_handler');

    $services->set(LanguageSubscriber::class)
        ->args([
            service(ElasticsearchHelper::class),
            service(ElasticsearchRegistry::class),
            service(Client::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DataCollector::class)
        ->args([
            param('elasticsearch.enabled'),
            param('elasticsearch.administration.enabled'),
            service(Client::class),
            service('admin.openSearch.client'),
        ])
        ->tag('data_collector', ['template' => '@Elasticsearch/Collector/elasticsearch.html.twig', 'id' => 'elasticsearch']);

    $services->alias('shopware.elasticsearch.logger', 'monolog.logger.elasticsearch');

    // This is required to prevent the 'Environment variables %VAR is never used' error
    $services->set('_dummy_es_env_usage', \ArrayIterator::class)
        ->lazy()
        ->public()
        ->args([
            [
                env('SHOPWARE_ES_ENABLED')->bool(),
                env('SHOPWARE_ES_INDEXING_ENABLED')->bool(),
                env('OPENSEARCH_URL')->string(),
                env('SHOPWARE_ES_INDEX_PREFIX')->string(),
                env('SHOPWARE_ES_THROW_EXCEPTION')->bool(),
                env('SHOPWARE_ES_INDEXING_BATCH_SIZE')->int(),
            ],
        ]);

    $services->set(RefreshIndexSubscriber::class)
        ->args([
            service(AdminSearchRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SystemInstallListener::class)
        ->args([
            service(ElasticsearchIndexer::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(SystemUpdateListener::class)
        ->args([
            service(AbstractKeyValueStorage::class),
            service(ElasticsearchIndexer::class),
            service('messenger.default_bus'),
            service(IndexMappingUpdater::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(AdminElasticsearchHelper::class)
        ->public()
        ->args([
            param('elasticsearch.administration.enabled'),
            param('elasticsearch.administration.refresh_indices'),
            param('elasticsearch.administration.index_prefix'),
            param('kernel.environment'),
            param('elasticsearch.administration.throw_exception'),
            service('shopware.elasticsearch.logger'),
        ]);

    $services->set(AdminCreateAliasTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('shopware.elasticsearch.logger'),
            service(AdminSearchRegistry::class),
            service(AdminElasticsearchHelper::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(AdminCreateAliasTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(AdminSearchController::class)
        ->public()
        ->args([
            service(AdminSearcher::class),
            service(DefinitionInstanceRegistry::class),
            service(JsonEntityEncoder::class),
            service(AdminElasticsearchHelper::class),
        ]);

    $services->set(AdminSearcher::class)
        ->args([
            service('admin.openSearch.client'),
            service(AdminSearchRegistry::class),
            service(AdminElasticsearchHelper::class),
            service(DefinitionInstanceRegistry::class),
            service(AbstractElasticsearchSearchHydrator::class),
            service(ElasticsearchHelper::class),
            param('elasticsearch.administration.search.timeout'),
            param('elasticsearch.administration.search.term_max_length'),
            param('elasticsearch.administration.search.search_type'),
        ]);

    $services->set(AdminSearchRegistry::class)
        ->args([
            tagged_iterator('shopware.elastic.admin-searcher-index', 'key'),
            service(Connection::class),
            service('messenger.default_bus'),
            service('event_dispatcher'),
            service('admin.openSearch.client'),
            service(AdminElasticsearchHelper::class),
            service('shopware.elasticsearch.logger'),
            param('elasticsearch.administration.index.config'),
            param('elasticsearch.administration.index.mapping'),
            param('kernel.environment'),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('messenger.message_handler');

    $services->set(CmsPageAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('cms_page.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'cms_page']);

    $services->set(CustomerAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('customer.repository'),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'customer']);

    $services->set(CustomerGroupAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('customer_group.repository'),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'customer_group']);

    $services->set(LandingPageAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('landing_page.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'landing_page']);

    $services->set(ManufacturerAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('product_manufacturer.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'product_manufacturer']);

    $services->set(MediaAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('media.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'media']);

    $services->set(OrderAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('order.repository'),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'order']);

    $services->set(PaymentMethodAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('payment_method.repository'),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'payment_method']);

    $services->set(ProductAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('product.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'product']);

    $services->set(PromotionAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('promotion.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'promotion']);

    $services->set(PropertyGroupAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('property_group.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'property_group']);

    $services->set(SalesChannelAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('sales_channel.repository'),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'sales_channel']);

    $services->set(ShippingMethodAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('shipping_method.repository'),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'shipping_method']);

    $services->set(CategoryAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('category.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'category']);

    $services->set(NewsletterRecipientAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('newsletter_recipient.repository'),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'newsletter_recipient']);

    $services->set(ProductStreamAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('product_stream.repository'),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('shopware.elastic.admin-searcher-index', ['key' => 'product_stream']);

    $services->set(ProductCriteriaParser::class)
        ->decorate(CriteriaParser::class)
        ->args([
            service(EntityDefinitionQueryHelper::class),
            service(CustomFieldService::class),
            service(AbstractKeyValueStorage::class),
            service(ProductCriteriaParser::class . '.inner'),
        ]);

    $services->set(ElasticsearchOptimizeSwitch::class)
        ->args([
            service(AbstractKeyValueStorage::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AdminElasticsearchEntitySearcher::class)
        ->decorate(EntitySearcherInterface::class, null, 500)
        ->public()
        ->args([
            service(AdminElasticsearchEntitySearcher::class . '.inner'),
            service(AdminSearchRegistry::class),
            service(AdminElasticsearchHelper::class),
            service(AdminSearcher::class),
            param('elasticsearch.administration.index_settings.max_result_window'),
        ]);
};
