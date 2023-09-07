<?php

use Scripts\Boot\ScriptKernel;
use Shopware\Core\HttpKernel;
use Symfony\Component\Dotenv\Dotenv;

$classLoader = require __DIR__ . '/../../../vendor/autoload.php';

require __DIR__ . '/ScriptKernel.php';

$projectRoot = dirname(__DIR__) . '/../../';

if (class_exists(Dotenv::class) && (file_exists($projectRoot . '/.env.local.php') || file_exists($projectRoot . '/.env') || file_exists($projectRoot . '/.env.dist'))) {
    (new Dotenv())->usePutenv()->bootEnv($projectRoot . '/.env');
}

$returnKernel = $returnKernel ?? false;

$env = $env ?? 'dev';

$kernel = new class($env, $env !== 'prod', $classLoader) extends HttpKernel {
    protected static string $kernelClass = ScriptKernel::class;
};

$kernel->getKernel()->boot();

return $kernel;
