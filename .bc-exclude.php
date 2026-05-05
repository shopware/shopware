<?php declare(strict_types=1);

use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;

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
        
      // Revert new feature, which was not released yet
        preg_quote('REMOVED: Class Shopware\Storefront\DependencyInjection\TwigComponentBundlePass has been deleted', '/'),
        preg_quote('REMOVED: Method Shopware\Storefront\Framework\Twig\Extension\ConfigExtension#componentImportMap() was removed', '/'),
        preg_quote('REMOVED: Method Shopware\Storefront\Framework\Twig\TemplateConfigAccessor#componentImportMap() was removed', '/'),
        preg_quote('REMOVED: Class Shopware\Storefront\Framework\Routing\StorybookRouteScopeAllowList has been deleted', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Framework\Bundle::getTwigComponentNamespace() was removed', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Framework\Bundle::getTwigComponentNamespace() was removed', '/'),

        /** Internal annotation on {@see SwTwigFunction} was not recognized correctly */
        preg_quote('CHANGED: Shopware\Core\Framework\Adapter\Twig\SwTwigFunction was marked "@internal"', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::escapeFilter() was removed', '/'),
        preg_quote('REMOVED: Method Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::resetEscapeCache() was removed', '/'),
    ],
];
