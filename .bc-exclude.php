<?php declare(strict_types=1);

return [
    'filePatterns' => [
        '**/Test/**', // Testing
        '**/src/WebInstaller/**', // WebInstaller TODO: remove after first 6.7 release
        '**/src/Core/Framework/Update/**', // Updater
        '**/src/Core/TestBootstrapper.php', // Testing
        '**/src/Core/Framework/Demodata/Faker/Commerce.php', // dev dependency
        '**/src/Core/DevOps/StaticAnalyze/**', // dev dependency
        '**/src/Core/Profiling/Doctrine/BacktraceDebugDataHolder.php', // dev dependency
        '**/src/Core/Migration/Traits/MigrationUntouchedDbTestTrait.php', // Test code in prod
        '**src/Core/Framework/Script/ServiceStubs.php', // never intended to be extended
        '**/src/Core/Framework/App/AppException.php', // intended to be internal
    ],
    'errors' => [
        // Don't complain about doctrine library changes
        'Doctrine\\\\DBAL',

        // Will be typed in Symfony 8 (maybe)
        preg_quote('Symfony\Component\Console\Command\Command#configure() changed from no type to void', '/'),

        // Version-related const values changed for the 7.3 update
        preg_quote('Value of constant Symfony\Component\HttpKernel\Kernel', '/'),

        // Cannot be inspected through reflection https://github.com/Roave/BetterReflection/issues/1376
        'An enum expression .* is not supported in .*',

        // Incorrectly deprecated
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Document\\\\DocumentException.* changed from self',
        preg_quote('The return type of Shopware\Core\Content\Product\ProductException::productNotFound() changed from self|Shopware\Core\Content\Product\Exception\ProductNotFoundException to Shopware\Core\Content\Product\Exception\ProductNotFoundException', '/'),

        // Expected to be appended when a new event is added
        preg_quote('Value of constant Shopware\Core\Framework\Webhook\Hookable', '/'),

        // Fix to make promotions work with order recalculation
        'Value of constant Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Order\\\\OrderConverter::ADMIN_EDIT_ORDER_PERMISSIONS changed from array \((\n.*)*skipPromotion.*(\n.*)*to array \((\n.*)*pinAutomaticPromotions',

        // Only new additions
        'Value of constant Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Order\\\\OrderConverter::ADMIN_EDIT_ORDER_PERMISSIONS changed from array \((\n.*)*to array \((\n.*)*skipCartPersistence(.*\n.*)*skipPrimaryOrderIds(.*\n.*)*automaticPromotionDeletionNotices',

        // No break as mixed is the top type, and every other type is a subtype of mixed
        preg_quote('The parameter $value of Shopware\Storefront\Event\StorefrontRenderEvent#setParameter() changed from no type to mixed', '/'),

        // No break as the `{get,set}SeoLink()` changes have not been released
        preg_quote('REMOVED: Property Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity#$seoLink was removed', '/'),
        'REMOVED: Method Shopware\\\\Core\\\\Content\\\\Category\\\\SalesChannel\\\\SalesChannelCategoryEntity#(get|set)SeoLink\(\) was removed',

        // The type has been extended and the old type is still accepted
        'CHANGED: The parameter \$context of Shopware\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\Extension\\\\BuildBreadcrumbExtension#(getFullBreadcrumb|getFullBreadcrumbById)\(\) changed from Shopware\\\\Core\\\\Framework\\\\Context to Shopware\\\\Core\\\\Framework\\\\Context\|Shopware\\\\Core\\\\System\\\\SalesChannel\\\\SalesChannelContext',

        // Widening the property type with null is necessary and not a BC break
        preg_quote('CHANGED: Type of property Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity#$type changed from Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeEntity to Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeEntity|null', '/'),
        preg_quote('CHANGED: Type of property Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity#$url changed from string to string|null', '/'),
        preg_quote('CHANGED: Type of property Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity#$mediaId changed from string to string|null', '/'),

        // Fix for promotion discount entity property initialization error - necessary to prevent runtime errors
        preg_quote('CHANGED: Type of property Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity#$sorterKey changed from string to string|null', '/'),
        preg_quote('CHANGED: Type of property Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity#$applierKey changed from string to string|null', '/'),
        preg_quote('CHANGED: The parameter $sorterKey of Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity#setSorterKey() changed from string to string|null', '/'),
        preg_quote('CHANGED: The parameter $applierKey of Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity#setApplierKey() changed from string to string|null', '/'),

        // The media thumbnail size id changes have not been released
        preg_quote('CHANGED: Type of property Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity#$mediaThumbnailSizeId changed from string to string|null', '/'),
        preg_quote('CHANGED: The return type of Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity#getMediaThumbnailSizeId() changed from string to the non-covariant string|null', '/'),
        preg_quote('CHANGED: The return type of Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity#getMediaThumbnailSizeId() changed from string to string|null', '/'),

        // The class has not been released
        'REMOVED: Class Shopware\\\\Elasticsearch\\\\Product\\\\CachedSearchConfigLoader has been deleted',

        // Removing empty constructor methods should not be a BC break
        preg_quote('REMOVED: Method Shopware\Core\Framework\DataAbstractionLayer\Attribute\AllowEmptyString#__construct() was removed', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Framework\DataAbstractionLayer\Attribute\Required#__construct() was removed', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey#__construct() was removed', '/'),

        // The rule class is declared @final, and its constructor is marked as @internal.
        preg_quote('REMOVED: Property Shopware\Core\Framework\Rule\Container\MatchAllLineItemsRule#$type was removed', '/'),

        // The classes were never properly released and are not use.
        preg_quote('REMOVED: Class Shopware\Core\System\Snippet\Struct\Language has been deleted', '/'),
        preg_quote('REMOVED: Class Shopware\Core\System\Snippet\Struct\SnippetPaths has been deleted', '/'),
        preg_quote('CHANGED: Type of property Shopware\Core\System\Snippet\Struct\TranslationConfig#$repositoryUrl changed from string to GuzzleHttp\Psr7\Uri', '/'),
        preg_quote('CHANGED: Type of property Shopware\Core\System\Snippet\Struct\TranslationConfig#$languages changed from Shopware\Core\System\Snippet\Struct\LanguageCollection to Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection', '/'),
        preg_quote('CHANGED: Type of property Shopware\Core\System\Snippet\Struct\TranslationConfig#$pluginMapping changed from array to Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection', '/'),
        preg_quote('ADDED: Parameter previous was added to Method translationConfigurationFileDoesNotExist() of class Shopware\Core\System\Snippet\SnippetException', '/'),

        // The constants were not aligned with the max length constants from the customer definition.
        preg_quote('CHANGED: Value of constant Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition::MAX_LENGTH_FIRST_NAME changed from 50 to 255', '/'),
        preg_quote('CHANGED: Value of constant Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition::MAX_LENGTH_LAST_NAME changed from 60 to 255', '/'),
        preg_quote('CHANGED: Value of constant Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition::MAX_LENGTH_FIRST_NAME changed from 50 to 255', '/'),
        preg_quote('CHANGED: Value of constant Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition::MAX_LENGTH_LAST_NAME changed from 60 to 255', '/'),

        preg_quote('Shopware\Core\Framework\App\AppException', '/'),
        preg_quote('Shopware\Core\Service\ServiceException', '/'),

        // The class has not been released
        preg_quote('CHANGED: The number of required arguments for Shopware\Core\System\SalesChannel\StoreApiResponse#__construct() increased from 1 to 2', '/'),
        preg_quote('CHANGED: The parameter $object of Shopware\Core\System\SalesChannel\StoreApiResponse#__construct() changed from Shopware\Core\Framework\Struct\Struct to a non-contravariant Shopware\Core\Content\Cookie\Struct\CookieGroupCollection', '/'),
        preg_quote('CHANGED: The parameter $object of Shopware\Core\System\SalesChannel\StoreApiResponse#__construct() changed from Shopware\Core\Framework\Struct\Struct to Shopware\Core\Content\Cookie\Struct\CookieGroupCollection', '/'),
        preg_quote('CHANGED: Parameter 0 of Shopware\Core\System\SalesChannel\StoreApiResponse#__construct() changed name from object to cookieGroups', '/'),
        preg_quote('Shopware\Core\Service\ServiceException', '/'),

        // remove after cookie changes have been released
        preg_quote('CHANGED: Property Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent#$salesChannelContext visibility reduced from public to protected', '/'),
        preg_quote('ADDED: Parameter request was added to Method __construct() of class Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent', '/'),
        preg_quote('CHANGED: The number of required arguments for Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent#__construct() increased from 2 to 3', '/'),
        preg_quote('CHANGED: The parameter $salesChannelContext of Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent#__construct() changed from Shopware\Core\System\SalesChannel\SalesChannelContext to a non-contravariant Symfony\Component\HttpFoundation\Request', '/'),
        preg_quote('CHANGED: The parameter $salesChannelContext of Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent#__construct() changed from Shopware\Core\System\SalesChannel\SalesChannelContext to Symfony\Component\HttpFoundation\Request', '/'),
        preg_quote('CHANGED: Parameter 1 of Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent#__construct() changed name from salesChannelContext to request', '/'),

        // Moved endpoint to Shopware\Core\Framework\Sso\Controller\SsoController to not have a hard dependency between admin and core packages
        // It was never intended to be used outside of SaaS in its initial release (still marked experimental / internal everywhere else, only this one method was forgotten)
        preg_quote('REMOVED: Method Shopware\Administration\Controller\AdministrationController#ssoAuth() was removed', '/'),

        // The "parts" arrays of these events could contain values that are not correctly represented in the getter and add methods. Those are necessary fixes, otherwise type errors will occur.
        preg_quote('CHANGED: The return type of Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent#get() changed from string', '/'),
        preg_quote('CHANGED: The return type of Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent#get() changed from string|null', '/'),
        preg_quote('CHANGED: The parameter $value of Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent#add() changed from string', '/'),

        // The property was wrongly added as it introduced a dependency on the Storefront package
        preg_quote('REMOVED: Property Shopware\Core\Content\Media\MediaEntity#$themes was removed', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Content\Media\MediaEntity#getThemes() was removed', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Content\Media\MediaEntity#setThemes() was removed', '/'),

        // Constants were introduced in the same release cycle
        preg_quote('REMOVED: Constant Shopware\Core\System\Snippet\SnippetValidator::LOCALE_PATTERN_BCP47_ISO639_1 was removed', '/'),
        preg_quote('REMOVED: Constant Shopware\Core\System\Snippet\SnippetValidator::SNIPPET_FILE_PATTERN was removed', '/'),

        // Domain exceptions should not be extended in 3rd party code
        preg_quote('ADDED: Parameter domain was added to Method invalidDomain() of class Shopware\Core\System\SystemConfig\SystemConfigException', '/'),
    ],
];
