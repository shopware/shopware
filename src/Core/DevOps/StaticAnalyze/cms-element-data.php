<?php declare(strict_types=1);

use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type\CmsElementDataPreparation;

$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';
CmsElementDataPreparation::prepare($root);
