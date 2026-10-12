<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @codeCoverageIgnore
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Api/Controller/UserController.php', 'attribute');
    $routes->import('../../Api/Controller/IntegrationController.php', 'attribute');
};
