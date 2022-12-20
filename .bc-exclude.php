<?php declare(strict_types=1);

return [
    'filePatterns' => [
        '**/Test/**', // Testing
        '**/src/Core/TestBootstrapper.php', // Testing
        '**/src/Core/Framework/Demodata/Faker/Commerce.php', // dev dependency
        '**/src/Core/DevOps/StaticAnalyze/**', // dev dependency
        '**/src/Core/Migration/Traits/MigrationUntouchedDbTestTrait.php', // Test code in prod
        '**/src/Elasticsearch/Framework/Command/ElasticsearchTestAnalyzerCommand.php', // Why?
        '**src/Core/Framework/Script/ServiceStubs.php', // never intended to be extended
        '**/src/Core/Migration/**', // Temporary until all migrations are internal
    ],
    'errors' => [
        'Shopware\\\\Core\\\\System\\\\SystemConfig\\\\Util\\\\ConfigReader#\\$xsdFile', // Can not be inspected through reflection (__DIR__ constant)
        'Shopware\\\\Core\\\\Framework\\\\Migration\\\\Exception\\\\UnknownMigrationSourceExceptionBase', // Can not be inspected through reflection if() {class Foo {} }
        'Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Doctrine\\\\RetryableTransaction::retryable()', // This is a static method so extending this class is not necessary
        'Value.+of.+constant', // Changing const values in not a BC per se
        '.+#__construct().+', // Todo make service constructors @internal

        // Should be safe
        'Method Shopware\\\\Storefront\\\\Framework\\\\Cache\\\\ReverseProxy\\\\FastlyReverseProxyGateway\\#\\_\\_destruct\\(\\) was removed'
    ],
];
