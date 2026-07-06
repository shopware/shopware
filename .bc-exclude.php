<?php declare(strict_types=1);

return [
    'filePatterns' => [
        '**/Test/**', // Testing
        '**/src/WebInstaller/**', // WebInstaller
        '**/src/Core/Framework/Update/**', // Updater
        '**/src/Core/TestBootstrapper.php', // Testing
        '**/src/Core/Framework/Demodata/Faker/Commerce.php', // dev dependency
        '**/src/Core/DevOps/StaticAnalyze/**', // dev dependency
        '**/src/Core/Profiling/Doctrine/BacktraceDebugDataHolder.php', // dev dependency
        '**/src/Core/Migration/Traits/MigrationUntouchedDbTestTrait.php', // Test code in prod
        '**src/Core/Framework/Script/ServiceStubs.php', // never intended to be extended
        '**/src/Core/Framework/App/Source/AbstractTemporaryDirectoryFactory.php', // dropped (not released yet)
        '**/src/Core/Framework/App/Source/TemporaryDirectoryFactory.php', // dropped decorator (not released yet)
        '**/src/Storefront/Framework/Twig/NavigationInfo.php', // new class (not released yet)
    ],
    'errors' => [
        // ProductReviewLoader moved to core, the entire class is deprecated, can be removed after the 6.7.0.0 release
        'Type of property Shopware\\\\Storefront\\\\Page\\\\Product\\\\Review\\\\ReviewLoaderResult#\\$.+ changed from .+ to having no type',
        'The return type of Shopware\\\\Storefront\\\\Page\\\\Product\\\\Review\\\\ReviewLoaderResult#.+ changed from .+ to .+',
        'Type of property Shopware\\\\Storefront\\\\Page\\\\Product\\\\Review\\\\ProductReviewsLoadedEvent#\\$.+ changed from .+ to having no type',
        'The return type of Shopware\\\\Storefront\\\\Page\\\\Product\\\\Review\\\\ProductReviewsLoadedEvent#.+ changed from .+ to .+',
        'The parameter .+ of Shopware\\\\Storefront\\\\Page\\\\Product\\\\Review\\\\ProductReviewsLoadedEvent#.+ changed from .+ to .+',

        // Will be typed in Symfony 8 (maybe)
        'Symfony\\\\Component\\\\Console\\\\Command\\\\Command#configure\(\) changed from no type to void',

        // False positive, when an object extends Symfony Command and has its own constructor
        '.* was added to Method __construct\(\) of class Symfony\\\\Component\\\\Console\\\\Command\\\\Command',
        preg_quote('Symfony\Component\Console\Command\Command#__construct()', '/'),

        'An enum expression .* is not supported in .*', // Cannot be inspected through reflection https://github.com/Roave/BetterReflection/issues/1376

        // Criteria is @final so changing from void should be fine
        'The return type of Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Search\\\\Criteria#setTitle\(\) changed from void',

        // Added new optional parameter to those classes
        'Parameter session was added to Method __construct\(\) of class Shopware\\\\Core\\\\System\\\\SalesChannel\\\\Event\\\\SalesChannelContextCreatedEvent',
        'Parameter collectionClass was added to Method __construct\(\) of class Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Attribute\\\\Entity',
        'Parameter cacheDir was added to Method createTwigEnvironment\(\) of class Shopware\\\\Core\\\\Content\\\\Seo\\\\SeoUrlTwigFactory',
        'Parameter serviceMenu was added to Method __construct\(\) of class Shopware\\\\Storefront\\\\Pagelet\\\\NavigationPagelet',
        'Parameter paymentMethods was added to Method __construct\(\) of class Shopware\\\\Storefront\\\\Pagelet\\\\NavigationPagelet',
        'Parameter shippingMethods was added to Method __construct\(\) of class Shopware\\\\Storefront\\\\Pagelet\\\\NavigationPagelet',

        // Changed $languageIdChain parameter to $context in TokenQueryBuilder
        'The parameter $languageIdChain of \\\\Shopware\\\\Elasticsearch\\\\TokenQueryBuilder#build\(\) changed from array to array|Shopware\\\\Core\\\\Framework\\\\Context',
        'Parameter 3 of Shopware\\\\Elasticsearch\\\\TokenQueryBuilder#build\(\) changed name from languageIdChain to context',
        'Parameter context was added to Method build\(\) of class Shopware\\\\Elasticsearch\\\\TokenQueryBuilder',

        // Changed SHOPWARE_FALLBACK_VERSION to comply with the latest composer changes, see: https://github.com/composer/composer/commit/1b5b56f234ab52a9dcfc935228d49e2a5e262e39
        'Value of constant Shopware\\\\Core\\\\Kernel::SHOPWARE_FALLBACK_VERSION changed from \'6.6.9999999.9999999-dev\' to \'6.6.9999999-dev\'',

        // The return type was incorrect and led to an error. It is not a breaking change if it's already breaking.
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Order\\\\OrderCollection#getOrderCustomers\(\) changed from Shopware\\\\Core\\\\Checkout\\\\Customer\\\\CustomerCollection to the non-covariant Shopware\\\\Core\\\\Checkout\\\\Order\\\\Aggregate\\\\OrderCustomer\\\\OrderCustomerCollection',
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Order\\\\OrderCollection#getOrderCustomers\(\) changed from Shopware\\\\Core\\\\Checkout\\\\Customer\\\\CustomerCollection to Shopware\\\\Core\\\\Checkout\\\\Order\\\\Aggregate\\\\OrderCustomer\\\\OrderCustomerCollection',

        // Version-related const values changed for the 7.2 update
        'Value of constant Symfony\\\\Component\\\\HttpKernel\\\\Kernel',

        // Class is marked as @final
        'Parameter clearHttp was added to Method clear\(\) of class Shopware\\\\Core\\\\Framework\\\\Adapter\\\\Cache\\\\CacheClearer',

        // Was not intended to be extended, declared as final
        'Class Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Attribute\\\\Entity became final',
        'Parameter hydratorClass was added to Method __construct\(\) of class Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Attribute\\\\Entity',

        'Parameter a11yMediaId was added to Method __construct\(\) of class Shopware\\\\Core\\\\Checkout\\\\Document\\\\DocumentIdStruct',

        // Changing from Exception to Throwable|null is widening and no problem
        'The parameter \\$previous of Shopware\\\\Core\\\\Framework\\\\Migration\\\\Exception\\\\MigrateException\\#__construct\(\) changed',

        // changing constructor in a safe way as long as you don't extend the hook
        'Parameter salesChannelId was added to Method __construct\(\) of class Shopware\\\\Core\\\\System\\\\SystemConfig\\\\Event\\\\SystemConfigChangedHook',

        // revert deprecated return value
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Document\\\\DocumentException::customerNotLoggedIn\(\) changed from self|Shopware\\\\Core\\\\Checkout\\\\Order\\\\Exception\\\\GuestNotAuthenticatedException to Shopware\\\\Core\\\\Checkout\\\\Order\\\\Exception\\\\GuestNotAuthenticatedException',
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Document\\\\DocumentException::guestNotAuthenticated\(\) changed from self|Shopware\\\\Core\\\\Checkout\\\\Order\\\\Exception\\\\GuestNotAuthenticatedException to Shopware\\\\Core\\\\Checkout\\\\Order\\\\Exception\\\\GuestNotAuthenticatedException',
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Document\\\\DocumentException::wrongGuestCredentials\(\) changed from self|Shopware\\\\Core\\\\Checkout\\\\Order\\\\Exception\\\\WrongGuestCredentialsException to Shopware\\\\Core\\\\Checkout\\\\Order\\\\Exception\\\\WrongGuestCredentialsException',

        // Fix to make promotions work with order recalculation
        'Value of constant Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Order\\\\OrderConverter::ADMIN_EDIT_ORDER_PERMISSIONS changed from array \((\n.*)*skipPromotion.*(\n.*)*to array \((\n.*)*pinAutomaticPromotions',

        'ADDED: Parameter visibility was added to Method __construct\(\) of class Shopware\\\\Core\\\\Framework\\\\Adapter\\\\Filesystem\\\\Plugin\\\\CopyBatchInput',

        'ADDED: Parameter versionId was added to Method createIterator\(\) of class Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Dbal\\\\Common\\\\IteratorFactory',

        // Added runtime parameter to Field attribute
        'ADDED: Parameter runtime was added to Method __construct\(\) of class Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Attribute\\\\Field',

        // The "parts" arrays of these events could contain values that are not correctly represented in the getter and add methods. Those are necessary fixes, otherwise type errors will occur.
        preg_quote('CHANGED: The return type of Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent#get() changed from string', '/'),
        preg_quote('CHANGED: The return type of Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent#get() changed from string|null', '/'),
        preg_quote('CHANGED: The parameter $value of Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent#add() changed from string', '/'),

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
    ],
];
