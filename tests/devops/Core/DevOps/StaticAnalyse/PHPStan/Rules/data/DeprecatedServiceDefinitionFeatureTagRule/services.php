<?php declare(strict_types=1);

use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\ParameterBag;

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

    // @deprecated tag:v6.8.0 Will be removed
    $services->alias('Shopware\Administration\Controller\NotificationController', 'current')
        ->deprecate('shopware/core', '6.7.0.0', 'Removed in v6.8.0.0');

    // @deprecated tag:v6.8.0 Will be removed
    $services->alias('unlisted-alias', 'current');

    // @deprecated tag:v6.9.0 Will be removed
    $services->alias('Shopware\Administration\Notification\NotificationDefinition', 'current');

    // @deprecated tag:v6.8.0 Will be removed
    $services->alias(ProductStreamBuilderInterface::class, 'current');

    // @deprecated tag:v6.8.0 This comment is not adjacent to the registration

    $services->alias('unmarked-alias', 'current');

    // @deprecated tag:v6.8.0 Will be removed
    $services->alias((string) getenv('LEGACY_ALIAS'), 'current');

    $service = $container->services();

    // @deprecated tag:v6.8.0 Will be removed
    $service->set('renamed-service');

    // @deprecated tag:v6.8.0 Will be removed
    $service->alias('renamed-alias', 'current');

    $services = new ParameterBag();

    // @deprecated tag:v6.8.0 This is not a service definition
    $services->set('unrelated', 'value');
};
