<?php declare(strict_types=1);

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $esIndexingEnabled = filter_var(
        EnvironmentHelper::getVariable('SHOPWARE_ES_INDEXING_ENABLED', false),
        \FILTER_VALIDATE_BOOL
    );

    $container->parameters()->set('shopware.product.search_keyword.indexing', !$esIndexingEnabled);
};
