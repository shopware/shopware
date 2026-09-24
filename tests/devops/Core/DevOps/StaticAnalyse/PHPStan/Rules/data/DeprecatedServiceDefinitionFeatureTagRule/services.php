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

    // @deprecated tag:v6.8.0 Will be removed
    $services->set('comment-missing');

    // @deprecated tag:v6.8.0 Will be removed
    $services->set('comment-wrong')
        ->tag('shopware.inactiveFeature', ['flag' => 'v6.9.0.0']);

    // @deprecated tag:v6.8.0 Will be removed
    $services->set('comment-correct')
        ->tag('shopware.inactiveFeature', ['flag' => 'v6.8.0.0']);

    // @deprecated tag:v6.8.0 This comment is not adjacent to the registration

    $services->set('after-blank-line');

    $services->alias('old', 'current')
        ->deprecate('shopware/core', '6.7.0.0', 'Removed in v6.8.0.0');
};
