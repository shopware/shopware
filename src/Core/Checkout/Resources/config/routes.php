<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @codeCoverageIgnore
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Cart/**/*Controller.php', 'attribute');
    $routes->import('../../Payment/**/*Controller.php', 'attribute');
    $routes->import('../../Cart/SalesChannel/**/*Controller.php', 'attribute');
    $routes->import('../../Customer/SalesChannel/**/*Controller.php', 'attribute');
    $routes->import('../../Document/**/*Controller.php', 'attribute');
    $routes->import('../../DocumentV2/**/*Controller.php', 'attribute');
    $routes->import('../../Promotion/**/*Controller.php', 'attribute');
    $routes->import('../../Order/Api/**/*Controller.php', 'attribute');
    $routes->import('../../Customer/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Payment/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Shipping/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Customer/Api/**/*Controller.php', 'attribute');
    $routes->import('../../Order/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Cart/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Document/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Gateway/SalesChannel/**/*Route.php', 'attribute');
};
