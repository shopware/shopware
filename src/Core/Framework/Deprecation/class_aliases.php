<?php declare(strict_types=1);

use Shopware\Core\Framework\Deprecation\ClassAliasRegistry;

foreach (ClassAliasRegistry::ALIASES as $previousClassName => $currentClassName) {
    if (class_exists($previousClassName, autoload: false)) {
        continue;
    }

    class_alias($currentClassName, $previousClassName);
}
