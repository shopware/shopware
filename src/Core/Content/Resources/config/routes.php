<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @codeCoverageIgnore
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Cms/SalesChannel/**/*Controller.php', 'attribute');
    $routes->import('../../Product/**/*Controller.php', 'attribute');
    $routes->import('../../Media/**/*Controller.php', 'attribute');
    $routes->import('../../Media/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../MailTemplate/**/*Controller.php', 'attribute');
    $routes->import('../../ImportExport/**/*Controller.php', 'attribute');
    $routes->import('../../Newsletter/**/*Controller.php', 'attribute');
    $routes->import('../../ProductExport/**/*Controller.php', 'attribute');
    $routes->import('../../Seo/Api/**/*Controller.php', 'attribute');
    $routes->import('../../Breadcrumb/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Cookie/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Category/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../LandingPage/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../LegalGuaranteeNotice/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Product/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Cms/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../ContactForm/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../RevocationRequest/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Newsletter/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Seo/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../ProductExport/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Sitemap/SalesChannel/**/*Route.php', 'attribute');
    $routes->import('../../Flow/**/*Controller.php', 'attribute');
};
