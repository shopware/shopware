<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Product\SearchKeyword\KeywordLoader;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter;
use Shopware\Core\Framework\Adapter\Lock\LockManager;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Sync\SyncFkResolver;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Api\Sync\Telemetry\SyncMetricsInstrumentor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Command\CreateEntitiesCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Command\CreateHydratorCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Command\CreateMigrationCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Command\DataAbstractionLayerValidateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Command\RefreshIndexCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaFieldsResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\ConfigJsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\CustomFieldsAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\PriceFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\CriteriaPartResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToManyAssociationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToOneAssociationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\OneToManyAssociationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\TranslationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinGroupBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\BlobFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\BoolFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CalculatedPriceFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CartPriceFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CashRoundingConfigFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ConfigJsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedAtFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedByFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CronIntervalFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateIntervalFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateTimeFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\EmailFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\EnumFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FkFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FloatFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IdFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IntFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToManyAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToManyAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToOneAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PHPUnserializeFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceDefinitionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ReferenceVersionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\RemoteAddressFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StateMachineStateFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TaxFreeConfigFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TimeZoneFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslatedFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslationsAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedAtFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedByFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\VariantListingConfigFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionDataPayloadFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\WasModifiedByUserFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\EntityIndexingSubscriber;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\RegisteredIndexerSubscriber;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Telemetry\IndexerMetricsInstrumentor;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationFileRenderer;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CachedCompressedCriteriaDecoder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CachedSearchConfigLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaArrayConverter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\DataAbstractionLayer\TechnicalNameExceptionHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\DalSearchInstrumentor;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityTelemetrySubscriber;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExistsValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityNotExistsValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Cleanup\CleanupVersionTask;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Cleanup\CleanupVersionTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteResultFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\LockValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ParentRelationValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Script\AppContextCreator;
use Shopware\Core\Framework\Telemetry\Metrics\Config\MetricConfigProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\Language\LanguageLoader;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        // @deprecated tag:v6.8.0 Will be removed, it's then always enabled
        ->set('env(SHOPWARE_DBAL_TIMEZONE_SUPPORT_ENABLED)', '0')
        ->set('shopware.dbal.time_zone_support_enabled', env('SHOPWARE_DBAL_TIMEZONE_SUPPORT_ENABLED')->bool())
        // @deprecated tag:v6.8.0 Will be removed
        ->set('env(SHOPWARE_DBAL_TOKEN_MINIMUM_LENGTH)', '3')
        // @deprecated tag:v6.8.0 Will be removed
        ->set('shopware.dbal.token_minimum_length', env('SHOPWARE_DBAL_TOKEN_MINIMUM_LENGTH')->int());

    $services = $containerConfigurator->services();

    $services->set(EntityGenerator::class);

    $services->set(CreateEntitiesCommand::class)
        ->args([
            service(EntityGenerator::class),
            service(DefinitionInstanceRegistry::class),
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(CreateMigrationCommand::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(MigrationQueryGenerator::class),
            service('kernel'),
            service(Filesystem::class),
            service(MigrationFileRenderer::class),
            param('kernel.shopware_core_dir'),
            param('kernel.shopware_version'),
            service(ClockInterface::class),
        ])
        ->tag('console.command');

    $services->set(SchemaBuilder::class);

    $services->set(MigrationFileRenderer::class);

    $services->set(MigrationQueryGenerator::class)
        ->args([
            service(Connection::class),
            service(SchemaBuilder::class),
        ]);

    $services->set(EntityLoadedEventFactory::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(CreateHydratorCommand::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Filesystem::class),
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(EntityCacheKeyGenerator::class)
        ->public();

    $services->set(EntityDefinitionQueryHelper::class);

    $services->set(JoinGroupBuilder::class)
        ->public();

    $services->set(EntityHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(DefinitionValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(Tokenizer::class)
        ->args([
            param('shopware.dbal.token_minimum_length'),
            param('shopware.search.preserved_chars'),
        ]);

    $services->set(SearchTermInterpreter::class)
        ->args([
            service(Tokenizer::class),
            param('shopware.dbal.token_minimum_length'),
        ]);

    $services->set(EntityScoreQueryBuilder::class);

    $services->set(ProductSearchTermInterpreter::class)
        ->args([
            service(Connection::class),
            service(Tokenizer::class),
            service('logger'),
            service(TokenFilter::class),
            service(KeywordLoader::class),
            service(SearchConfigLoader::class),
            param('shopware.product.search_keyword.relevant_keyword_count'),
        ]);

    $services->set(KeywordLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set('api.request_criteria_builder', RequestCriteriaBuilder::class)
        ->args([
            service(AggregationParser::class),
            service(ApiCriteriaValidator::class),
            service(CriteriaArrayConverter::class),
            service(CompressedCriteriaDecoder::class),
            param('shopware.api.max_limit'),
        ]);

    $services->set(SearchConfigLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CachedSearchConfigLoader::class)
        ->decorate(SearchConfigLoader::class, null, -1000)
        ->args([
            service(CachedSearchConfigLoader::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(CriteriaArrayConverter::class)
        ->args([
            service(AggregationParser::class),
        ]);

    $services->set(CompressedCriteriaDecoder::class);

    $services->set(CachedCompressedCriteriaDecoder::class)
        ->decorate(CompressedCriteriaDecoder::class)
        ->args([
            service(CachedCompressedCriteriaDecoder::class . '.inner'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(RequestCriteriaBuilder::class)
        ->args([
            service(AggregationParser::class),
            service(ApiCriteriaValidator::class),
            service(CriteriaArrayConverter::class),
            service(CompressedCriteriaDecoder::class),
            param('shopware.api.store.max_limit'),
        ]);

    $services->set(ApiCriteriaValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(AggregationParser::class);

    $services->set(RepositoryFacadeHookFactory::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(AppContextCreator::class),
            service(RequestCriteriaBuilder::class),
            service(AclCriteriaValidator::class),
        ]);

    $services->set(RepositoryWriterFacadeHookFactory::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(AppContextCreator::class),
            service(SyncService::class),
        ]);

    $services->set(SalesChannelRepositoryFacadeHookFactory::class)
        ->public()
        ->args([
            service(SalesChannelDefinitionInstanceRegistry::class),
            service(RequestCriteriaBuilder::class),
        ]);

    // EntityDefinition[]
    $services->set(EntityReaderInterface::class, EntityReader::class)
        ->public()
        ->args([
            service(Connection::class),
            service(EntityHydrator::class),
            service(EntityDefinitionQueryHelper::class),
            service(SqlQueryParser::class),
            service(CriteriaQueryBuilder::class),
            service('logger'),
            service(CriteriaFieldsResolver::class),
        ]);

    $services->set(CriteriaFieldsResolver::class);

    $services->set(EntityAggregatorInterface::class, EntityAggregator::class)
        ->public()
        ->args([
            service(Connection::class),
            service(EntityDefinitionQueryHelper::class),
            service(DefinitionInstanceRegistry::class),
            service(CriteriaQueryBuilder::class),
            param('shopware.dbal.time_zone_support_enabled'),
            service(SearchTermInterpreter::class),
            service(EntityScoreQueryBuilder::class),
        ]);

    $services->set(EntitySearcherInterface::class, EntitySearcher::class)
        ->public()
        ->args([
            service(Connection::class),
            service(EntityDefinitionQueryHelper::class),
            service(CriteriaQueryBuilder::class),
        ]);

    $services->set(CriteriaQueryBuilder::class)
        ->args([
            service(SqlQueryParser::class),
            service(EntityDefinitionQueryHelper::class),
            service(SearchTermInterpreter::class),
            service(EntityScoreQueryBuilder::class),
            service(JoinGroupBuilder::class),
            service(CriteriaPartResolver::class),
        ]);

    $services->set(CriteriaPartResolver::class)
        ->args([
            service(Connection::class),
            service(SqlQueryParser::class),
        ]);

    $services->set(EntityWriter::class)
        ->public()
        ->args([
            service(WriteCommandExtractor::class),
            service(EntityForeignKeyResolver::class),
            service(EntityWriteGatewayInterface::class),
            service(LanguageLoader::class),
            service(DefinitionInstanceRegistry::class),
            service(EntityWriteResultFactory::class),
        ]);

    $services->set(EntityWriteResultFactory::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(WriteCommandExtractor::class)
        ->args([
            service(EntityWriteGatewayInterface::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(EntityWriteGatewayInterface::class, EntityWriteGateway::class)
        ->public()
        ->args([
            param('shopware.dal.batch_size'),
            service(Connection::class),
            service('event_dispatcher'),
            service(ExceptionHandlerRegistry::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(ConstraintBuilder::class);

    $services->set(SqlQueryParser::class)
        ->args([
            service(EntityDefinitionQueryHelper::class),
            service(Connection::class),
        ]);

    $services->set(EntityForeignKeyResolver::class)
        ->args([
            service(Connection::class),
            service(EntityDefinitionQueryHelper::class),
        ]);

    $services->set(ManyToOneAssociationFieldResolver::class)
        ->args([
            service(EntityDefinitionQueryHelper::class),
            service(Connection::class),
        ])
        ->tag('shopware.field_resolver', ['priority' => -50]);

    $services->set(OneToManyAssociationFieldResolver::class)
        ->tag('shopware.field_resolver', ['priority' => -50]);

    $services->set(ManyToManyAssociationFieldResolver::class)
        ->tag('shopware.field_resolver', ['priority' => -50]);

    $services->set(TranslationFieldResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.field_resolver', ['priority' => -50]);

    $services->set(PriceFieldAccessorBuilder::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.field_accessor_builder', ['priority' => -100]);

    $services->set(JsonFieldAccessorBuilder::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.field_accessor_builder', ['priority' => -150]);

    $services->set(DefaultFieldAccessorBuilder::class)
        ->tag('shopware.field_accessor_builder', ['priority' => -200]);

    $services->set(ConfigJsonFieldAccessorBuilder::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.field_accessor_builder', ['priority' => -100]);

    $services->set(CustomFieldsAccessorBuilder::class)
        ->args([
            service(CustomFieldService::class),
            service(Connection::class),
        ])
        ->tag('shopware.field_accessor_builder', ['priority' => -100]);

    $services->set(VersionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(VersionCommitDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(VersionCommitDataDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(RefreshIndexCommand::class)
        ->args([
            service(EntityIndexerRegistry::class),
            service('event_dispatcher'),
            service('messenger.default_bus'),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('console.command');

    $services->set(RegisteredIndexerSubscriber::class)
        ->args([
            service(IndexerQueuer::class),
            service(EntityIndexerRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DataAbstractionLayerValidateCommand::class)
        ->args([
            service(DefinitionValidator::class),
        ])
        ->tag('console.command');

    $services->set(VersionManager::class)
        ->public()
        ->args([
            service(EntityWriter::class),
            service(EntityReaderInterface::class),
            service(EntitySearcherInterface::class),
            service(EntityWriteGatewayInterface::class),
            service('event_dispatcher'),
            service('serializer'),
            service(DefinitionInstanceRegistry::class),
            service(VersionCommitDefinition::class),
            service(VersionCommitDataDefinition::class),
            service(VersionDefinition::class),
            service(LockManager::class),
            service(ClockInterface::class),
        ]);

    $services->set(CalculatedPriceFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(CartPriceFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(CashRoundingConfigFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(TaxFreeConfigFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(PriceDefinitionFieldSerializer::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('validator'),
            service(RuleConditionRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(BoolFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(WasModifiedByUserFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(CreatedAtFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(ClockInterface::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(DateFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(DateTimeFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(EmailFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(EnumFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(FkFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(StateMachineStateFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(FloatFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(IdFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(IntFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(RemoteAddressFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(SystemConfigService::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(JsonFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(ConfigJsonFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(LongTextFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(HtmlSanitizer::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(ListFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(ManyToManyAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(ManyToOneAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(OneToOneAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(BlobFieldSerializer::class)
        ->tag('shopware.field_serializer');

    $services->set(OneToManyAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(EntityWriteGatewayInterface::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(PasswordFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(SystemConfigService::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(PHPUnserializeFieldSerializer::class)
        ->tag('shopware.field_serializer');

    $services->set(PriceFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(VariantListingConfigFieldSerializer::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('validator'),
        ])
        ->tag('shopware.field_serializer');

    $services->set(ReferenceVersionFieldSerializer::class)
        ->tag('shopware.field_serializer');

    $services->set(StringFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(HtmlSanitizer::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(TranslatedFieldSerializer::class)
        ->tag('shopware.field_serializer');

    $services->set(TranslationsAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(EntityWriteGatewayInterface::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(UpdatedAtFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(ClockInterface::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(VersionDataPayloadFieldSerializer::class)
        ->tag('shopware.field_serializer');

    $services->set(VersionFieldSerializer::class)
        ->tag('shopware.field_serializer');

    $services->set(CustomFieldsSerializer::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('validator'),
            service(CustomFieldService::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(CreatedByFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(UpdatedByFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(TimeZoneFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(CronIntervalFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(DateIntervalFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(EntityExistsValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(EntitySearcherInterface::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(EntityNotExistsValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(EntitySearcherInterface::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(IteratorFactory::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(DefinitionInstanceRegistry::class)
        ->public()
        ->args([
            service('service_container'),
            [],
            [],
        ]);

    $services->set(LockValidator::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ParentRelationValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SyncService::class)
        ->public()
        ->args([
            service(EntityWriter::class),
            service('event_dispatcher'),
            service(DefinitionInstanceRegistry::class),
            service(EntitySearcherInterface::class),
            service(RequestCriteriaBuilder::class),
            service(AclCriteriaValidator::class),
            service(SyncFkResolver::class),
            service(SyncMetricsInstrumentor::class),
        ]);

    $services->set(SyncMetricsInstrumentor::class)
        ->args([
            service(Meter::class),
            service(EntityGroupResolver::class),
        ]);

    $services->set(SyncFkResolver::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            tagged_iterator('shopware.sync.fk_resolver'),
        ]);

    $services->set(ExceptionHandlerRegistry::class)
        ->args([
            tagged_iterator('shopware.dal.exception_handler'),
        ]);

    $services->set(TechnicalNameExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(EntityProtectionValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(EntityIndexerRegistry::class)
        ->public()
        ->args([
            tagged_iterator('shopware.entity_indexer'),
            service('messenger.default_bus'),
            service('event_dispatcher'),
            service(IndexerMetricsInstrumentor::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(IndexerMetricsInstrumentor::class)
        ->args([
            service(Meter::class),
        ]);

    $services->set(EntityIndexingSubscriber::class)
        ->args([
            service(EntityIndexerRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(InheritanceUpdater::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(ChildCountUpdater::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(ManyToManyIdFieldUpdater::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(CleanupVersionTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(CleanupVersionTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            param('shopware.dal.versioning.expire_days'),
            service(ClockInterface::class),
            service(EventDispatcherInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(EntityTelemetrySubscriber::class)
        ->args([
            service(Meter::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopware.telemetry.subscriber');

    // shared entity-name bucketing for telemetry labels (DAL search collectors + HTTP request domain)
    $services->set(EntityGroupResolver::class);

    // injected into every EntityRepository by EntityCompilerPass; self-gates on the telemetry flag
    $services->set(DalSearchInstrumentor::class)
        ->args([
            service(Meter::class),
            service(EntityGroupResolver::class),
            service(MetricConfigProvider::class),
            param('shopware.telemetry.metrics.enabled'),
        ]);
};
