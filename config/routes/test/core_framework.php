<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('../../../tests/integration/Core/Framework/Api/EventListener/FixturesPhp/*TestRoute.php', 'attribute');
    $routes->import('../../../tests/integration/Core/Content/Seo/SalesChannel/FixturesPhp/*TestRoute.php', 'attribute');
};
