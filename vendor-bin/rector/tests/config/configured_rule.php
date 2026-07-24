<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use Shopware\Core\DevOps\StaticAnalyze\Rector\EntitySearchResultGetEntitiesRector;

return RectorConfig::configure()
    ->withRules([EntitySearchResultGetEntitiesRector::class]);