<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @codeCoverageIgnore
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('@PentatrionViteBundle/Resources/config/routing.yaml')
        ->prefix('/build');

    $routes->add('_profiler_vite', '/_profiler/vite')
        ->controller('Pentatrion\ViteBundle\Controller\ProfilerController::info');
};
