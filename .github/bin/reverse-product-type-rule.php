#!/usr/bin/env php
<?php

declare(strict_types=1);

use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Kernel;
use Shopware\Core\Migration\V6_6\Migration1775922924ReverseLineItemProductTypeRuleCondition;
use Symfony\Component\Dotenv\Dotenv;

$classLoader = require __DIR__ . '/../../vendor/autoload.php';

if (is_file(__DIR__ . '/../../.env')) {
    (new Dotenv())->usePutenv()->bootEnv(__DIR__ . '/../../.env');
}

$pluginLoader = new DbalKernelPluginLoader($classLoader, null, Kernel::getConnection());

$kernel = KernelFactory::create('dev', true, $classLoader, $pluginLoader);
$kernel->boot();

$conn = $kernel->getContainer()->get(\Doctrine\DBAL\Connection::class);

echo 'Reversing cartLineItemProductType → cartLineItemProductStates for blue-green compatibility' . PHP_EOL;

$migration = new Migration1775922924ReverseLineItemProductTypeRuleCondition();
$migration->update($conn);

echo 'Done' . PHP_EOL;
