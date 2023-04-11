<?php declare(strict_types=1);

return [
    'filePatterns' => [
        '**/Test/**', // Testing
        '**/src/Recovery/**', // Testing
        '**/src/Recovery/**', // Recovery
        '**/src/Core/Framework/Update/**', // Updater
        '**/src/Core/TestBootstrapper.php', // Testing
        '**/src/Core/Framework/Demodata/Faker/Commerce.php', // dev dependency
        '**/src/Core/DevOps/StaticAnalyze/**', // dev dependency
        '**/src/Core/Profiling/Doctrine/BacktraceDebugDataHolder.php', // dev dependency
        '**/src/Core/Migration/Traits/MigrationUntouchedDbTestTrait.php', // Test code in prod
        '**src/Core/Framework/Script/ServiceStubs.php', // never intended to be extended
        '**/src/Core/DevOps/System/Command/SyncComposerVersionCommand.php', // symfony configure
    ],
    'errors' => [
        'Shopware\\\\Core\\\\System\\\\SystemConfig\\\\Util\\\\ConfigReader#\\$xsdFile', // Can not be inspected through reflection (__DIR__ constant)
        'Shopware\\\\Core\\\\Framework\\\\Migration\\\\Exception\\\\UnknownMigrationSourceExceptionBase', // Can not be inspected through reflection if() {class Foo {} }
        'Unable to compile initializer in method', // Can not be inspected through reflection https://github.com/Roave/BackwardCompatibilityCheck/issues/698
        'Could not locate constant .* while trying to evaluate constant expression', // Can not be inspected through reflection https://github.com/Roave/BackwardCompatibilityCheck/issues/698
        'Value.+of.+constant', // Changing const values in not a BC per se
        'Property Shopware\\\\Core\\\\System\\\\Currency\\\\CurrencyEntity#\\$shippingMethodPrices was removed',
        'Method Shopware\\\\Core\\\\System\\\\Currency\\\\CurrencyEntity#getShippingMethodPrices\\(\\) was removed',
        'Method Shopware\\\\Core\\\\System\\\\Currency\\\\CurrencyEntity#setShippingMethodPrices\\(\\) was removed',
        'Property Shopware\\\\Core\\\\Checkout\\\\Shipping\\\\Aggregate\\\\ShippingMethodPrice\\\\ShippingMethodPriceEntity#\\$currency was removed',
        'Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\SchemaIndexListener',
        'Shopware\\\\Core\\\\Framework\\\\Log\\\\LoggerFactory#createRotating\\(\\)',
        'Shopware\\\\Core\\\\Content\\\\MailTemplate\\\\Service\\\\Event\\\\MailErrorEvent#__construct\\(\\)',

        // internal typos -> should be removed with 6.5.1.0 release
        'Shopware\\\\Core\\\\Framework\\\\App\\\\AppPayloadServiceHelper was marked "@internal"',
        'Shopware\\\\Core\\\\Framework\\\\App\\\\Command\\\\CreateAppCommand was marked "@internal"',
        'Shopware\\\\Core\\\\Framework\\\\Log\\\\Package was marked "@internal"',
        'Shopware\\\\Core\\\\Content\\\\Mail\\\\MailerConfigurationCompilerPass was marked "@internal"',
        'Shopware\\\\Core\\\\Checkout\\\\Cart\\\\TaxProvider\\\\Struct\\\\TaxProviderResult was marked "@internal"',
        'Shopware\\\\Core\\\\Checkout\\\\Cart\\\\CachedRuleLoader was marked "@internal"',

        // Fixes to comply with parent method signature to fix LSP -> should be removed with 6.5.1.0 release
        'The parameter .* of .*(ReverseProxyCache|StructDecoder|InvalidLimitQueryException|InvalidPageQueryException|QueryLimitExceededException|CustomFields|NestedEventDispatcher|LanguageNotFoundException|WebhookDispatcher|HappyPathValidator|JsonApiDecoder|EntityPipe|Kernel|CountSort).*\(\) changed from',
        'Method .*\(\) of class .*(ThemeCompileCommand|ThemeSalesChannelCollection|CachedCountryRoute|CachedCountryStateRoute|SalesChannelAnalyticsDefinition|PluginRecommendationCollection|PluginCategoryCollection|LicenseDomainCollection|PluginRegionCollection|StateMachineStateField|AssetInstallCommand|JsonApiResponse|SystemDumpDatabaseCommand|SystemRestoreDatabaseCommand|DocsAppEventCommand|ImportExportProfileTranslationDefinition|UpdateByCollection|MappingCollection|ProductCrossSellingAssignedProductsCollection|ProductCrossSellingAssignedProductsDefinition|ProductCrossSellingCollection|ProductCrossSellingDefinition|ProductFeatureSetTranslationDefinition|CrossSellingElementCollection|SalesChannelProductCollection|PromotionTranslationDefinition|PromotionDiscountPriceCollection) visibility reduced from',
        'Shopware\\\\Core\\\\Framework\\\\Adapter\\\\Filesystem\\\\Filesystem' // not used and deprecated bundle class
    ],
];
