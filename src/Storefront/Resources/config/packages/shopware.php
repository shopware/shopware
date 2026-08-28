<?php declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()->set('shopware.storefront.redirect_on_single_hit_fields', [
        'productNumber',
        'ean',
        'manufacturerNumber',
    ]);
};
