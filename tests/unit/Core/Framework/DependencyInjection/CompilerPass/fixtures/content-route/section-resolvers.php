<?php declare(strict_types=1);

use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Registers one section resolver per path-less section, `header` and `footer`, so the route pass
 * generates their store-api routes next to the `main` section routes from Core's DI file.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('content_system.section_resolver.header', RenderingSpecificationResolver::class)
        ->tag('content_system.section_resolver', ['section' => 'header']);

    $services->set('content_system.section_resolver.footer', RenderingSpecificationResolver::class)
        ->tag('content_system.section_resolver', ['section' => 'footer']);
};
