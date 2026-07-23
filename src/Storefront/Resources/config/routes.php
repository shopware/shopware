<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @codeCoverageIgnore
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Controller/**/*Controller.php', 'attribute');
    $routes->import('../../Theme/**/*Controller.php', 'attribute');
};
