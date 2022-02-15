<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Api;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
class CustomEntityRouteLoader extends Loader
{
    private bool $isLoaded = false;

    public function load($resource, ?string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new HttpException('custom_entity_route_loader_already_loaded', 'Do not add the "custom entity api route" loader twice');
        }

        $routes = new RouteCollection();

        $this->loadRoutes($routes);

        $this->isLoaded = true;

        return $routes;
    }

    public function supports($resource, ?string $type = null): bool
    {
        return $type === 'custom-entity';
    }

    private function loadRoutes(RouteCollection $routes): void
    {
        // uuid followed by any number of '/{entity-name}/{uuid}' | '/extensions/{entity-name}/{uuid}' pairs followed by an optional slash
        $detailSuffix = '[0-9a-f]{32}(\/(extensions\/)?[a-zA-Z-]+\/[0-9a-f]{32})*\/?$';

        // '/{uuid}/{entity-name}' | '/{uuid}/extensions/{entity-name}' pairs followed by an optional slash
        $listSuffix = '(\/[0-9a-f]{32}\/(extensions\/)?[a-zA-Z-]+)*\/?$';

        $class = CustomEntityApiController::class;

        // detail routes
        $route = new Route('/api/custom-entity-{entityName}/{path}');
        $route->setMethods(['GET']);
        $route->setDefault('_controller', $class . '::detail');
        $route->addRequirements(['path' => $detailSuffix, 'version' => '\d+']);
        $routes->add('api.custom_entity_entity.detail', $route);

        $route = new Route('/api/custom-entity-{entityName}/{path}');
        $route->setMethods(['PATCH']);
        $route->setDefault('_controller', $class . '::update');
        $route->addRequirements(['path' => $detailSuffix, 'version' => '\d+']);
        $routes->add('api.custom_entity_entity.update', $route);

        $route = new Route('/api/custom-entity-{entityName}/{path}');
        $route->setMethods(['DELETE']);
        $route->setDefault('_controller', $class . '::delete');
        $route->addRequirements(['path' => $detailSuffix, 'version' => '\d+']);
        $routes->add('api.custom_entity_entity.delete', $route);

        // list routes
        $route = new Route('/api/custom-entity-{entityName}{path}');
        $route->setMethods(['GET']);
        $route->setDefault('_controller', $class . '::list');
        $route->addRequirements(['path' => $listSuffix, 'version' => '\d+']);
        $routes->add('api.custom_entity_entity.list', $route);

        $route = new Route('/api/search/custom-entity-{entityName}{path}');
        $route->setMethods(['POST']);
        $route->setDefault('_controller', $class . '::search');
        $route->addRequirements(['path' => $listSuffix, 'version' => '\d+']);
        $routes->add('api.custom_entity_entity.search', $route);

        $route = new Route('/api/search-ids/custom-entity-{entityName}{path}');
        $route->setMethods(['POST']);
        $route->setDefault('_controller', $class . '::searchIds');
        $route->addRequirements(['path' => $listSuffix, 'version' => '\d+']);
        $routes->add('api.custom_entity_entity.search-ids', $route);

        $route = new Route('/api/custom-entity-{entityName}{path}');
        $route->setMethods(['POST']);
        $route->setDefault('_controller', $class . '::create');
        $route->addRequirements(['path' => $listSuffix, 'version' => '\d+']);
        $routes->add('api.custom_entity_entity.create', $route);
    }
}
