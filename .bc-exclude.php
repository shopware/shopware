<?php declare(strict_types=1);

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
        '**/src/Core/DevOps/System/Command/SyncComposerVersionCommand.php', // symfony configure

        // Symfony validators, should be removed with NEXT-26264
        '**/src/Core/Framework/DataAbstractionLayer/Validation/EntityNotExists.php',
        '**/src/Core/Framework/DataAbstractionLayer/Validation/EntityExists.php',
        '**/src/Core/Checkout/Customer/Validation/Constraint/CustomerVatIdentification.php',
        '**/src/Core/Checkout/Customer/Validation/Constraint/CustomerEmailUnique.php',
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
        'Shopware\\\\Core\\\\Framework\\\\Adapter\\\\Filesystem\\\\Filesystem', // not used and deprecated bundle class

        // base hook class was marked as internal -> now as final
        'Hook::getDeprecatedServices\\(\\)',
        'Shopware\\\\Core\\\\Framework\\\\Script\\\\Execution\\\\Hook#__construct\\(\\) (was removed|was marked "@internal")',
        'Shopware\\\\Core\\\\Framework\\\\Script\\\\Execution\\\\Hook#getName\\(\\) (was removed|was marked "@internal")',

        // internal visibility fixes based on parent classes
        'Shopware\\\\Core\\\\System\\\\CustomEntity\\\\Xml\\\\Field\\\\.* was marked "@internal"',
        'Shopware\\\\Storefront\\\\Page\\\\PageLoadedHook was marked "@internal"',
        'Shopware\\\\Storefront\\\\Framework\\\\App\\\\Template\\\\IconTemplateLoader was marked "@internal"',
        'Shopware\\\\Core\\\\System\\\\SalesChannel\\\\StoreApiRequestHook was marked "@internal"',
        'Shopware\\\\Core\\\\Content\\\\Seo\\\\Entity\\\\Dbal\\\\SeoUrlAssociationFieldResolver was marked "@internal"',

        // __set cannot have a return value
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Document\\\\DocumentConfiguration#__set\(\) changed from no type to void',

        // Should be removed with NEXT-29044
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Customer\\\\SalesChannel\\\\CustomerRecoveryIsExpiredRoute#getDecorated\(\) changed from Shopware\\\\Core\\\\Checkout\\\\Customer\\\\SalesChannel\\\\AbstractResetPasswordRoute to the non-covariant Shopware\\\\Core\\\\Checkout\\\\Customer\\\\SalesChannel\\\\AbstractCustomerRecoveryIsExpiredRoute',
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Customer\\\\SalesChannel\\\\CustomerRecoveryIsExpiredRoute#getDecorated\(\) changed from Shopware\\\\Core\\\\Checkout\\\\Customer\\\\SalesChannel\\\\AbstractResetPasswordRoute to Shopware\\\\Core\\\\Checkout\\\\Customer\\\\SalesChannel\\\\AbstractCustomerRecoveryIsExpiredRoute',
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Customer\\\\SalesChannel\\\\AbstractCustomerRecoveryIsExpiredRoute#getDecorated\(\) changed from Shopware\\\\Core\\\\Checkout\\\\Customer\\\\SalesChannel\\\\AbstractResetPasswordRoute to the non-covariant Shopware\\\\Core\\\\Checkout\\\\Customer\\\\SalesChannel\\\\AbstractCustomerRecoveryIsExpiredRoute',
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Customer\\\\SalesChannel\\\\AbstractCustomerRecoveryIsExpiredRoute#getDecorated\(\) changed from Shopware\\\\Core\\\\Checkout\\\\Customer\\\\SalesChannel\\\\AbstractResetPasswordRoute to Shopware\\\\Core\\\\Checkout\\\\Customer\\\\SalesChannel\\\\AbstractCustomerRecoveryIsExpiredRoute',

        // Renaming of arguments
        'Parameter 1 of Shopware\\\\Elasticsearch\\\\Framework\\\\Indexing\\\\IndexerOffset#__construct\(\) changed name from definitions to mappingDefinitions'
    ],
];
