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

        // Version-related const values changed for the 7.3 update
        preg_quote('Value of constant Symfony\Component\HttpKernel\Kernel', '/'),

        // Cannot be inspected through reflection https://github.com/Roave/BetterReflection/issues/1376
        'An enum expression .* is not supported in .*',

        // Incorrectly deprecated
        'The return type of Shopware\\\\Core\\\\Checkout\\\\Document\\\\DocumentException.* changed from self',
        preg_quote('The return type of Shopware\Core\Content\Product\ProductException::productNotFound() changed from self|Shopware\Core\Content\Product\Exception\ProductNotFoundException to Shopware\Core\Content\Product\Exception\ProductNotFoundException', '/'),

        // Expected to be appended when a new event is added
        preg_quote('Value of constant Shopware\Core\Framework\Webhook\Hookable', '/'),

        // No break as mixed is the top type, and every other type is a subtype of mixed
        preg_quote('CHANGED: The return type of Shopware\Core\Framework\Util\Random::getRandomArrayElement() changed from no type to mixed', '/'),

        // Domain exceptions should not be extended in 3rd party code
        preg_quote('ADDED: Parameter domain was added to Method invalidDomain() of class Shopware\Core\System\SystemConfig\SystemConfigException', '/'),

        // Type widening from string to ParsedRobots|string is backward compatible - all existing string usage continues to work
        preg_quote('CHANGED: The parameter $rules of Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct#__construct() changed from string to Shopware\Storefront\Page\Robots\Parser\ParsedRobots|string', '/'),

        // Should have been internal in the first place, all the other changelog classes were internal and already removed
        preg_quote('REMOVED: Class Shopware\Core\Framework\Changelog\ChangelogSection has been deleted', '/'),
        preg_quote('REMOVED: Class Shopware\Core\Framework\Changelog\ChangelogKeyword has been deleted', '/'),

        // JWTGenerator not released yet
        preg_quote('ADDED: Parameter disableValidation was added to Method decode() of class Shopware\Core\Framework\JWT\SalesChannel\JWTGenerator', '/'),
        preg_quote('ADDED: Parameter jwt was added to Method getTokenLifetime() of class Shopware\Core\Framework\JWT\SalesChannel\JWTGenerator', '/'),
        preg_quote('CHANGED: The number of required arguments for Shopware\Core\Framework\JWT\SalesChannel\JWTGenerator#getTokenLifetime() increased from 0 to 1', '/'),

        // consumed not released yet
        preg_quote('REMOVED: Property Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct#$consumed was removed', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct#isConsumed() was removed', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct#setConsumed() was removed', '/'),

        // Fix for promotion discount entity property initialization error - necessary to prevent runtime errors
        preg_quote('CHANGED: Type of property Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity#$usageKey changed from string to string|null', '/'),
        preg_quote('CHANGED: The parameter $usageKey of Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity#setUsageKey() changed from string to string|null', '/'),

        // Fix for @internal annotation placed in the wrong place
        preg_quote('REMOVED: Class Shopware\Core\Framework\Adapter\Cache\Http\CacheHashService has been deleted', '/'),
        // Had a typo in the internal annotation
        preg_quote('CHANGED: Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder was marked "@internal"', '/'),

        // PDF will be the default when no $fileType is given and Accept header is not present or is a wildcard
        preg_quote('CHANGED: Default parameter value for parameter $fileType of Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute#download() changed from \'pdf\' to NULL', '/'),

        // Inherited attribute $reversed parameter removed - attribute inheritance never worked before, so no BC break
        preg_quote('REMOVED: Property Shopware\Core\Framework\DataAbstractionLayer\Attribute\Inherited#$reversed was removed', '/'),
        preg_quote('Shopware\Core\Framework\DataAbstractionLayer\Attribute\Inherited#__construct()', '/'),

        // Defined entity property mismatch the entity class property type
        'Type of property Shopware\\\\.*\\\\OrderTransactionCaptureEntity#$stateMachineState changed .* to Shopware\\\\.*\\\\StateMachineStateEntity|null',
        'The return type of Shopware\\\\.*\\\\OrderTransactionCaptureEntity#getStateMachineState() changed .* Shopware\\\\.*\\\\StateMachineStateEntity|null',
        'The parameter $stateMachineState of Shopware\\\\.*\\\\OrderTransactionCaptureEntity#setStateMachineState() changed .* Shopware\\\\.*\\\\StateMachineStateEntity|null',
    ],
];
