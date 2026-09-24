<?php declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('missing')
        ->deprecate('shopware/core', '6.7.0.0', 'Removed in v6.8.0');

    $services->set('wrong')
        ->deprecate('shopware/core', '6.7.0.0', 'Removed in v6.8.0.0')
        ->tag('shopware.inactiveFeature', ['flag' => 'v6.9.0.0']);

    $services->set('correct')
        ->deprecate('shopware/core', '6.7.0.0', 'Removed in v6.8.0.0')
        ->tag('shopware.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set('current');

    $services->alias('old', 'current')
        ->deprecate('shopware/core', '6.7.0.0', 'Removed in v6.8.0.0');
};
