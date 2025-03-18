<?php

$php = ['8.2'];
$db = ['mysql:8.0'];

$nightly = $_SERVER['argv'][1] ?? false;

if ($nightly) {
    $php = ['8.2', '8.4'];
    $db = ['mysql:8.0', 'mariadb:11'];
}

echo \json_encode([
    'fail-fast' => false,
    'matrix' => [
        'test' => [
            ['path' => 'Core/Checkout'],
            ['path' => 'Core/Content'],
            ['path' => '{Core/Framework/Adapter,Core/Framework/Api,Core/Framework/App,Core/Framework/Cache,Core/Framework/Changelog,Core/Framework/CustomField}'],
            ['path' => '{Core/Framework/DataAbstractionLayer,Core/Framework/Demodata,Core/Framework/DependencyInjection,Core/Framework/FeatureFlag,Core/Framework/Increment,Core/Framework/Language,Core/Framework/Log,Core/Framework/MessageQueue}'],
            ['path' => '{Core/Framework/Migration,Core/Framework/Plugin,Core/Framework/RateLimiter,Core/Framework/Routing,Core/Framework/Rule,Core/Framework/Script,Core/Framework/Seo,Core/Framework/Store,Core/Framework/Telemetry,Core/Framework/TestCaseBase}'],
            ['path' => '{Core/Framework/Translation,Core/Framework/Update,Core/Framework/Util,Core/Framework/Webhook,Core/Framework/AdditionalPermissionValidationTest.php,Core/Framework/ApiRoutesHaveASchemaTest.php,Core/Framework/KernelTest.php,Core/Framework/ServiceDefinitionTest.php}'],
            ['path' => 'Storefront'],
            ['path' => '{Administration,Elasticsearch}'],
            ['path' => '{Core/Installer,Core/Maintenance,Core/System}'],
            ['testsuite' => 'migration'],
            ['testsuite' => 'devops']
        ],
        'php' => $php,
        'db' => $db,
    ]
], \JSON_THROW_ON_ERROR);
