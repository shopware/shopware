<?php declare(strict_types=1);

use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type\CmsElementReturnTypeExtension;

return [
    'parameters' => ['scanFiles' => [__DIR__ . '/CustomPage.php']],
    'services' => [
        [
            'class' => CmsElementReturnTypeExtension::class,
            'arguments' => ['mappingFile' => __DIR__ . '/types.json'],
            'tags' => ['phpstan.broker.dynamicMethodReturnTypeExtension'],
        ],
    ],
];
