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
    ],
    'errors' => [
        // Will be typed in Symfony 8 (maybe)
        'Symfony\\\\Component\\\\Console\\\\Command\\\\Command#configure\(\) changed from no type to void',

        'An enum expression .* is not supported in .*', // Can not be inspected through reflection https://github.com/Roave/BetterReflection/issues/1376
        // major
        'Value of constant Shopware\\\\Core\\\\Kernel::SHOPWARE_FALLBACK_VERSION changed from \'6.6.9999999-dev\' to \'6.7.9999999-dev\'',
    ],
];
