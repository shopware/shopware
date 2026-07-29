<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @codeCoverageIgnore
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Api/Controller/**/*Controller.php', 'attribute');
    $routes->import('../../Plugin/**/*Controller.php', 'attribute');
    $routes->import('../../Store/**/*Controller.php', 'attribute');
    $routes->import('../../Update/**/*Controller.php', 'attribute');
    $routes->import('../../MessageQueue/**/*Controller.php', 'attribute');
    $routes->import('../../Increment/Controller/*Controller.php', 'attribute');
    $routes->import('../../Migration/**/*Controller.php', 'attribute');
    $routes->import('../../App/**/*Controller.php', 'attribute');
    $routes->import('../../App/**/*Route.php', 'attribute');
    $routes->import('../../Script/Api/*Route.php', 'attribute');
    $routes->import('../../Rule/Api/*Controller.php', 'attribute');
    $routes->import('../../Gateway/**/*Route.php', 'attribute');
    $routes->import('../../Sso/Controller/**/*Controller.php', 'attribute');
    $routes->import('../../Mcp/Controller/*Controller.php', 'attribute');
    $routes->import('../../Validation/Api/*Controller.php', 'attribute');
};
