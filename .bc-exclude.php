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

        // No break as mixed is the top type, and every other type is a subtype of mixed
        preg_quote('CHANGED: The return type of Shopware\Core\Framework\Util\Random::getRandomArrayElement() changed from no type to mixed', '/'),

        // Domain exceptions should not be extended in 3rd party code
        preg_quote('ADDED: Parameter domain was added to Method invalidDomain() of class Shopware\Core\System\SystemConfig\SystemConfigException', '/'),

        // Should have been internal in the first place, all the other changelog classes were internal and already removed
        preg_quote('REMOVED: Class Shopware\Core\Framework\Changelog\ChangelogSection has been deleted'),
        preg_quote('REMOVED: Class Shopware\Core\Framework\Changelog\ChangelogKeyword has been deleted'),
    ],
];
