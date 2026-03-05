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

        // Had a typo in the internal annotation
        preg_quote('CHANGED: Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder was marked "@internal"', '/'),

        // Inherited attribute $reversed parameter removed - attribute inheritance never worked before, so no BC break
        preg_quote('REMOVED: Property Shopware\Core\Framework\DataAbstractionLayer\Attribute\Inherited#$reversed was removed', '/'),
        preg_quote('Shopware\Core\Framework\DataAbstractionLayer\Attribute\Inherited#__construct()', '/'),

        // Defined entity property mismatch the entity class property type
        'Type of property Shopware\\\\.*\\\\OrderTransactionCaptureEntity#$stateMachineState changed .* to Shopware\\\\.*\\\\StateMachineStateEntity|null',
        'The return type of Shopware\\\\.*\\\\OrderTransactionCaptureEntity#getStateMachineState() changed .* Shopware\\\\.*\\\\StateMachineStateEntity|null',
        'The parameter $stateMachineState of Shopware\\\\.*\\\\OrderTransactionCaptureEntity#setStateMachineState() changed .* Shopware\\\\.*\\\\StateMachineStateEntity|null',

        preg_quote('CHANGED: Property Shopware\Core\Content\ProductStream\ProductStreamEntity#$internal changed default value from NULL to false', '/'),

        // No break as all existing NoContentResponse usages are still valid with the widened StoreApiResponse return type
        'CHANGED: The return type of Shopware\\\\Core\\\\Content\\\\Newsletter\\\\SalesChannel\\\\.* changed from Shopware\\\\Core\\\\System\\\\SalesChannel\\\\NoContentResponse to (?:the non-covariant )?Shopware\\\\Core\\\\System\\\\SalesChannel\\\\StoreApiResponse',

        // Injecting request parameter into controller method is not a BC break
        preg_quote('ADDED: Parameter request was added to Method clearDelayedCache() of class Shopware\Core\Framework\Api\Controller\CacheController', '/'),
        preg_quote('CHANGED: The number of required arguments for Shopware\Core\Framework\Api\Controller\CacheController#clearDelayedCache() increased from 0 to 1', '/'),

        // SystemDumpDatabaseCommand was not marked @internal
        preg_quote('CHANGED: Shopware\\Core\\DevOps\\System\\Command\\SystemDumpDatabaseCommand was marked "@internal"', '/'),
        preg_quote('REMOVED: Method Shopware\\Core\\DevOps\\System\\Command\\SystemDumpDatabaseCommand#getIgnoreTableStmt() was removed', '/'),

        // SystemRestoreDatabaseCommand was marked @internal
        preg_quote('CHANGED: Shopware\\Core\\DevOps\\System\\Command\\SystemRestoreDatabaseCommand was marked "@internal"', '/'),
    ],
];
