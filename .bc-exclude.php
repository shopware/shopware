<?php declare(strict_types=1);

use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;

return [
    'filePatterns' => [
        '**/Test/**', // Testing
        '**/src/Core/Framework/Update/**', // Updater
        '**/src/Core/TestBootstrapper.php', // Testing
        '**/src/Core/Framework/Demodata/Faker/Commerce.php', // dev dependency
        '**/src/Core/DevOps/StaticAnalyze/**', // dev dependency
        '**/src/Core/Profiling/Doctrine/BacktraceDebugDataHolder.php', // dev dependency
        '**/src/Core/Migration/Traits/MigrationUntouchedDbTestTrait.php', // Test code in prod
        '**src/Core/Framework/Script/ServiceStubs.php', // never intended to be extended
        '**/tests/unit/Core/DevOps/Docs/Script/_fixtures/**', // Testing
        '**/src/Core/Framework/App/AppException.php', // intended to be internal
    ],
    'errors' => [
        // Don't complain about doctrine library changes
        'Doctrine\\\\DBAL',

        // Will be typed in Symfony 8 (maybe)
        preg_quote('Symfony\Component\Console\Command\Command#configure() changed from no type to void', '/'),

        // False positive, when an object extends Symfony Command and has its own constructor
        '.* was added to Method __construct\(\) of class Symfony\\\\Component\\\\Console\\\\Command\\\\Command',
        preg_quote('Symfony\Component\Console\Command\Command#__construct()', '/'),

        // Cannot be inspected through reflection https://github.com/Roave/BetterReflection/issues/1376
        'An enum expression .* is not supported in .*',

        // Expected to be appended when a new event is added
        preg_quote('Value of constant Shopware\Core\Framework\Webhook\Hookable', '/'),

        // Expected to be appended when a new default admin user privilege is added; existing entries are never removed
        preg_quote('Value of constant Shopware\Core\Framework\Api\Context\AdminApiSource::DEFAULT_USER_PRIVILEGES', '/'),

        // Intentional rename of the technical-term analyzer chain so the public
        // identifier matches how the chain is referenced everywhere else
        // (constants, `buildTextFieldConfig(technicalTerms: true)`, the
        // architecture doc). Shopware-internal users were already going through
        // `ElasticsearchFieldBuilder::ANALYZER_WHITESPACE_TECHNICAL_*` and the
        // `TECHNICAL_TERM_SEARCH_FIELD` const — both still resolve correctly;
        // only the underlying analyzer string moved from
        // `sw_*_word_delimiter_*_analyzer` to `sw_*_technical_term_*_analyzer`.
        // Documented in UPGRADE-6.8.md.
        preg_quote('Value of constant Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder::ANALYZER_WHITESPACE_TECHNICAL_INDEX', '/'),
        preg_quote('Value of constant Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder::ANALYZER_WHITESPACE_TECHNICAL_SEARCH', '/'),
        preg_quote('Value of constant Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition::TECHNICAL_TERM_SEARCH_FIELD', '/'),

        // Had a typo in the internal annotation
        preg_quote('CHANGED: Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder was marked "@internal"', '/'),

        // SystemDumpDatabaseCommand was not marked @internal
        preg_quote('CHANGED: Shopware\\Core\\DevOps\\System\\Command\\SystemDumpDatabaseCommand was marked "@internal"', '/'),
        preg_quote('REMOVED: Method Shopware\\Core\\DevOps\\System\\Command\\SystemDumpDatabaseCommand#getIgnoreTableStmt() was removed', '/'),

        // Plugin lifecycle command constructors were not marked @internal
        preg_quote('REMOVED: Method Shopware\Core\Framework\Plugin\Command\Lifecycle\AbstractPluginLifecycleCommand#__construct() was removed', '/'),
        preg_quote('ADDED: Parameter projectDir was added to Method __construct() of class Shopware\Core\Framework\Plugin\Command\Lifecycle\AbstractPluginLifecycleCommand', '/'),
        preg_quote('CHANGED: Shopware\Core\Framework\Plugin\Command\Lifecycle\AbstractPluginLifecycleCommand#__construct() was marked "@internal"', '/'),
        preg_quote('CHANGED: The number of required arguments for Shopware\Core\Framework\Plugin\Command\Lifecycle\AbstractPluginLifecycleCommand#__construct() increased from 3 to 4', '/'),

        // No break as all existing NoContentResponse usages are still valid with the widened StoreApiResponse return type
        'CHANGED: The return type of Shopware\\\\Core\\\\Content\\\\Newsletter\\\\SalesChannel\\\\.* changed from Shopware\\\\Core\\\\System\\\\SalesChannel\\\\NoContentResponse to (?:the non-covariant )?Shopware\\\\Core\\\\System\\\\SalesChannel\\\\StoreApiResponse',

        // class is @final, so making a parameter nullable is not a breaking change
        preg_quote('CHANGED: The parameter $fileType of Shopware\Core\Checkout\Document\Service\DocumentGenerator#readDocument() changed from string to string|null', '/'),

        // SystemRestoreDatabaseCommand was marked @internal
        preg_quote('CHANGED: Shopware\\Core\\DevOps\\System\\Command\\SystemRestoreDatabaseCommand was marked "@internal"', '/'),

        // Unused protected method from final class can be removed safely
        preg_quote('REMOVED: Method Shopware\Core\Framework\Store\InAppPurchase\Services\DecodedPurchaseStruct#throwException() was removed', '/'),

        // TaxProviderPersister was mistakenly not marked @internal
        preg_quote('CHANGED: Shopware\Core\Framework\App\Lifecycle\Persister\TaxProviderPersister was marked "@internal"', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Framework\App\Lifecycle\Persister\TaxProviderPersister#updateTaxProviders() was removed', '/'),

        // Constants should be `float` to reflect the expected type
        preg_quote('CHANGED: Value of constant Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking::', '/'),

        // Return type is still of type "self" but more specific. Could never be something different from the InvalidSortQueryException, so this should be fine
        'CHANGED: The return type of Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\DataAbstractionLayerException.* changed from self to (?:the non-covariant )?Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Exception\\\\InvalidSortQueryException',

        // minor library update, no break
        preg_quote(' OpenSearch\Client', '/'),
        // widening input argument in exception factory, no break
        preg_quote('CHANGED: The parameter $previous of Shopware\Elasticsearch\Product\ElasticsearchProductException::cannotChangeFieldType() changed from OpenSearch\Common\Exceptions\BadRequest400Exception to OpenSearch\Common\Exceptions\BadRequest400Exception|OpenSearch\Exception\BadRequestHttpException', '/'),
        preg_quote('CHANGED: The parameter $previous of Shopware\Elasticsearch\Product\ElasticsearchProductException::cannotChangeCustomFieldType() changed from OpenSearch\Common\Exceptions\BadRequest400Exception to OpenSearch\Common\Exceptions\BadRequest400Exception|OpenSearch\Exception\BadRequestHttpException', '/'),
        // constructor changes of internal decorator, no break
        preg_quote('ADDED: Parameter transport was added to Method __construct() of class Shopware\Elasticsearch\Profiler\ClientProfiler', '/'),
        preg_quote('CHANGED: Parameter 0 of Shopware\Elasticsearch\Profiler\ClientProfiler#__construct() changed name from client to transport', '/'),

        /** Internal annotation on {@see SwTwigFunction} was not recognized correctly */
        preg_quote('CHANGED: Shopware\Core\Framework\Adapter\Twig\SwTwigFunction was marked "@internal"', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::escapeFilter() was removed', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::resetEscapeCache() was removed', '/'),

        // The implemented Twig extension contract already documents this as array<NodeVisitorInterface>
        preg_quote('CHANGED: The return type of Twig\Extension\AbstractExtension#getNodeVisitors() changed from no type to array', '/'),

        // Twig added this method in 3.27 via https://github.com/twigphp/Twig/pull/4816
        preg_quote('REMOVED: Method Twig\TokenParser\AbstractTokenParser#isAlwaysAllowedInSandbox() was removed', '/'),

        // swagger-php 6.4 is required for OpenAPI 3.2 generation. The reported
        // BC changes are in the third-party OpenApi\Analysis API. Extensions
        // that only define OpenAPI annotations/attributes continue to work; code
        // using swagger-php programmatically has a documented migration path in
        // RELEASE_INFO-6.7.md / UPGRADE-6.7.md.
        preg_quote('REMOVED: Method OpenApi\Analysis#getSchemaForSource() was removed', '/'),
        preg_quote('REMOVED: Method OpenApi\Analysis#getContext() was removed', '/'),
        preg_quote('REMOVED: Method OpenApi\Analysis#process() was removed', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$annotations changed from having no type to SplObjectStorage', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$classes changed from having no type to array', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$interfaces changed from having no type to array', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$traits changed from having no type to array', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$enums changed from having no type to array', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$openapi changed from having no type to OpenApi\Annotations\OpenApi|null', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$context changed from having no type to OpenApi\Context|null', '/'),
        preg_quote('CHANGED: The parameter $annotation of OpenApi\Analysis#addAnnotation() changed from object', '/'),
        preg_quote('CHANGED: The parameter $class of OpenApi\Analysis#getSuperClasses() changed from string to string|null', '/'),
        preg_quote('CHANGED: The parameter $class of OpenApi\Analysis#getInterfacesOfClass() changed from string to string|null', '/'),
        preg_quote('CHANGED: The parameter $source of OpenApi\Analysis#getTraitsOfClass() changed from string to string|null', '/'),
        preg_quote('ADDED: Parameter sourceClass was added to Method getAnnotationForSource() of class OpenApi\Analysis', '/'),
        preg_quote('CHANGED: Parameter 1 of OpenApi\Analysis#getAnnotationForSource() changed name from class to sourceClass', '/'),
        preg_quote('CHANGED: The return type of OpenApi\Analysis#split() changed from no type to stdClass', '/'),

        // MailDataSimulatorFieldEvent no longer exposes Faker in the runtime simulate feature
        preg_quote('REMOVED: Property Shopware\Core\Content\MailTemplate\Service\Event\MailDataSimulatorFieldEvent#$faker was removed', '/'),
        preg_quote('REMOVED: Parameter faker was removed from Method Shopware\Core\Content\MailTemplate\Service\Event\MailDataSimulatorFieldEvent::__construct()', '/'),

        // Optional parameter added with default null; existing callers are unaffected
        preg_quote('ADDED: Parameter introducedIn was added to Method triggerDeprecationOrThrow() of class Shopware\Core\Framework\Feature', '/'),

        // Rule classes are tagged @final
        preg_quote('CHANGED: Type of property Shopware\Core\Checkout\Customer\Rule\CustomerBirthdayRule#$birthday changed from string|null to string|array|null', '/'),
        preg_quote('CHANGED: Type of property Shopware\Core\Checkout\Cart\Rule\LineItemReleaseDateRule#$lineItemReleaseDate changed from string|null to string|array|null', '/'),
        preg_quote('CHANGED: Type of property Shopware\Core\Checkout\Cart\Rule\LineItemCreationDateRule#$lineItemCreationDate changed from string|null to string|array|null', '/'),
        preg_quote('REMOVED: Property Shopware\Core\Checkout\Cart\Rule\LineItemPurchasePriceRule#$isNet was removed', '/'),
        preg_quote('CHANGED: The return type of Shopware\Core\Framework\Rule\Rule#getConfig() changed from Shopware\Core\Framework\Rule\RuleConfig|null to Shopware\Core\Framework\Rule\RuleConfig', '/'),

        // DefinitionValidator is @final; optional parameter added with default [], existing callers are unaffected
        preg_quote('ADDED: Parameter toleratedNonStandardForeignKeys was added to Method validate() of class Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator', '/'),

        // DocumentType translations were incorrectly typed as product translations
        preg_quote('CHANGED: Type of property Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity#$translations changed from Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection|null', '/'),
        preg_quote('CHANGED: The return type of Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity#getTranslations() changed from Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection|null', '/'),
        preg_quote('CHANGED: The parameter $translations of Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity#setTranslations() changed from Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection', '/'),

        // Contravariant widening so the filter also accepts PartialEntity media from partial listing loading
        preg_quote('The parameter $media of Shopware\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter#encodeMediaUrl() changed from', '/'),

        // Experimental MCP feature (gated behind the MCP_SERVER flag, all MCP classes are
        // @experimental stableVersion:v6.8.0). The MCP rate-limit route was split per API
        // scope, replacing the single RateLimiter::MCP constant with MCP_ADMIN_API /
        // MCP_STORE_API. The constant lived on the non-experimental RateLimiter class so it
        // was not auto-skipped, but it is part of the still-experimental MCP surface.
        preg_quote('REMOVED: Constant Shopware\Core\Framework\RateLimiter\RateLimiter::MCP was removed', '/'),

        // EntitySearchResult::merge() takes EntityCollection (not self) so it accepts any collection, not just other search results.
        'CHANGED: The parameter \$collection of Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\EntityCollection#merge\(\) changed from self to (?:a non-contravariant )?Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\EntityCollection',

        // Translated CustomerGroupEntity properties are now nullable like the translation entity (fixes #16461).
        preg_quote('CHANGED: Type of property Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#$registrationTitle changed from string to string|null', '/'),
        preg_quote('CHANGED: Type of property Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#$registrationIntroduction changed from string to string|null', '/'),
        preg_quote('CHANGED: Type of property Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#$registrationOnlyCompanyRegistration changed from bool to bool|null', '/'),
        preg_quote('CHANGED: Type of property Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#$registrationSeoMetaDescription changed from string to string|null', '/'),
        preg_quote('CHANGED: The return type of Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#getRegistrationTitle() changed from string', '/'),
        preg_quote('CHANGED: The return type of Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#getRegistrationIntroduction() changed from string', '/'),
        preg_quote('CHANGED: The return type of Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#getRegistrationOnlyCompanyRegistration() changed from bool', '/'),
        preg_quote('CHANGED: The return type of Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#getRegistrationSeoMetaDescription() changed from string', '/'),

        // parent method has no type. not really a break
        preg_quote('CHANGED: The return type of Shopware\Core\Framework\Migration\Command\RefreshMigrationCommand#configure() changed from void to ', '/'),

        // intended to be internal on release
        preg_quote('CHANGED: Shopware\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface was marked "@internal"', '/'),
        preg_quote('CHANGED: Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver was marked "@internal"', '/'),

        // Only thrown during the legacy commercial license sync; all services
        // that used it have published versions using the new webhooks instead
        preg_quote('REMOVED: Constant Shopware\Core\Service\ServiceException::SERVICE_MISSING_APP_SECRET_INFO was removed', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Service\ServiceException::missingAppSecretInfo() was removed', '/'),

    ],
];
