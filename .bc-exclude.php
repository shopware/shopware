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
        '**/src/Core/Checkout/Payment/Cart/PaymentHandler/PreparedPaymentHandlerInterface.php', // remove with FEATURE_NEXT_16769

        // ToDo: NEXT-19323 - Remove temporary added excludes
        '**/ArrayFacade.php',
        '**/ContainerFacade.php',
        '**/ErrorsFacade.php',
        '**/ProductsFacade.php',
        '**/ItemsFacade.php',
        '**/ItemsIteratorTrait.php',
        '**/ItemsAddTrait.php',
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
        'Symfony\\\\Component\\\\HttpKernel\\\\Kernel#\\$bundles',

        // OpenAPI library update
        'The return type of Shopware\\\\Core\\\\Framework\\\\Api\\\\ApiDefinition\\\\Generator\\\\OpenApi\\\\DeactivateValidationAnalysis#validate',
        'OpenApi\\\\Analysis',

        'Method Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Facade\\\\Traits\\\\ContainerFactoryTrait#container\(\) was removed', // NEXT-19501
        'Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Facade\\\\Traits\\\\ContainerFactoryTrait#container\(\) was marked "@internal',  // NEXT-19501

        // BC changes between last release and trunk
        'Shopware\\\\Storefront\\\\Page\\\\Product\\\\Configurator\\\\AvailableCombinationLoader was marked "@internal"',
        'The return type of Shopware\\\\Core\\\\System\\\\StateMachine\\\\StateMachineEntity#getName',
        'The return type of Shopware\\\\Core\\\\System\\\\StateMachine\\\\StateMachineEntity#setName',
        'The parameter \\$name of Shopware\\\\Core\\\\System\\\\StateMachine\\\\StateMachineEntity#setName\(\) changed from string to \?string',
        'The return type of Symfony\\\\Component\\\\Console\\\\Command\\\\Command#configure',
        'Class Shopware\\\\Core\\\\Framework\\\\MessageQueue\\\\MonitoringBusDecorator has been deleted',
        'Class Shopware\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\InheritanceExtension has been deleted',
        'Class Shopware\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\InstanceOfExtension has been deleted',
        'Class Shopware\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\FeatureFlagExtension has been deleted',
        'Shopware\\\\Core\\\\Framework\\\\Update\\\\Api\\\\UpdateController was marked "@internal"',
        'Shopware\\\\Core\\\\Framework\\\\Update\\\\Services\\\\PluginCompatibility was marked "@internal"',
        'Method Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Facade\\\\CartFacade#addState\(\) was removed',
        'Method Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Facade\\\\CartFacade#removeState\(\) was removed',
        'Method Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Facade\\\\CartFacade#hasState\(\) was removed',
        'Method Shopware\\\\Core\\\\Checkout\\\\Cart\\\\Facade\\\\CartFacade#getStates\(\) was removed'
    ],
];
