#!/usr/bin/env php
<?php

declare(strict_types=1);

use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;

$classLoader = require __DIR__ . '/../../vendor/autoload.php';

if (is_file(__DIR__ . '/../../.env')) {
    (new Dotenv())->usePutenv()->bootEnv(__DIR__ . '/../../.env');
}

$pluginLoader = new DbalKernelPluginLoader($classLoader, null, Kernel::getConnection());

$kernel = KernelFactory::create('dev', true, $classLoader, $pluginLoader);

$kernel->boot();

$application = new Application($kernel);
$application->setName('Shopware');
$application->setVersion($kernel->getContainer()->getParameter('kernel.shopware_version'));
$application->setDefaultCommand('dal:validate', true);
$application->setCatchErrors(false);
$application->setCatchExceptions(false);
$application->setAutoExit(false);

$output = new BufferedOutput();
$output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

echo 'Running blue-green-compatibility test' . \PHP_EOL;
$exitCode = $application->run(new ArrayInput(['--json' => true]), $output);
if ($exitCode === Command::SUCCESS) {
    echo 'No issues found' . \PHP_EOL;

    return Command::SUCCESS;
}

$outputString = $output->fetch();
$errorsPerClass = json_decode($outputString, true, 512, \JSON_THROW_ON_ERROR);

foreach ($errorsPerClass as $class => $errors) {
    $errorsPerClass[$class] = array_filter($errors, static function (string $error): bool {
        return str_ends_with($error, 'has no configured column');
    });
    if ($errorsPerClass[$class] === []) {
        unset($errorsPerClass[$class]);
    }
}

if ($errorsPerClass === []) {
    echo 'No issues found' . \PHP_EOL;
    return Command::SUCCESS;
}

$errorMessage = "Blue-green-compatibility test failed due to missing columns in the database: \n";
foreach ($errorsPerClass as $class => $missingDbColumn) {
    $errorMessage .= 'Table "' . $class::ENTITY_NAME . "\", Columns:\n";
    foreach ($missingDbColumn as $column) {
        $errorMessage .= ' - ' . $column . "\n";
    }
}

echo $errorMessage;

return Command::FAILURE;
