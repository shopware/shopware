<?php declare(strict_types=1);

return [
    'filePatterns' => [
        '**/src/Docs/**', // Deprecated
        '**/Test/**', // Testing
        '**/src/Core/TestBootstrapper.php', // Testing
        '**/src/Core/Framework/Demodata/Faker/Commerce.php', // dev dependency
        '**/src/Core/DevOps/StaticAnalyze/**', // dev dependency

        '**/src/Core/Migration/Traits/MigrationUntouchedDbTestTrait.php', // Test code in prod
        '**/src/Elasticsearch/Framework/Command/ElasticsearchTestAnalyzerCommand.php', // Why?
    ],
    'errors' => [
        'Shopware\\\\Core\\\\System\\\\SystemConfig\\\\Util\\\\ConfigReader#\\$xsdFile', // Can not be inspected through reflection (__DIR__ constant)
        'Shopware\\\\Core\\\\Framework\\\\Migration\\\\Exception\\\\UnknownMigrationSourceExceptionBase', // Can not be inspected through reflection if() {class Foo {} }
        'Type.+documentation.+for.+property', // Doc type to native type conversions seems to not correctly be detected by the BC checker
        'Shopware\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\Doctrine\\\\RetryableTransaction::retryable()', // This is a static method so extending this class is not necessary
        'The.+#__construct().+', // Todo make service constructors @internal
        'Default.+#__construct().+', // Todo make service constructors @internal
        'The return type of Shopware\\\\Core\\\\Framework\\\\Changelog\\\\Command\\\\Changelog(Check|Change|Create)Command#execute\(\) changed from no type to int',
        'Symfony\\\\Component\\\\HttpFoundation\\\\Response::\\$statusTexts',
        'Symfony\\\\Component\\\\HttpKernel\\\\Kernel#\\$bundles'
    ],
];
