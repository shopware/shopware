<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Controller\ContentController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
#[Package('discovery')]
class StorefrontContentRouteLoader extends Loader
{
    public const PARAMETER_ENTITY_ID = 'contentSystemEntityId';
    public const ATTRIBUTE_PATH_PREFIX = '_content_system_path_prefix';
    private const ROUTE_TYPE = 'content_system_storefront';

    private bool $isLoaded = false;

    /**
     * @param iterable<AbstractContentLayoutAssignableDefinition> $assignableDefinitions
     */
    public function __construct(
        private readonly iterable $assignableDefinitions,
    ) {
        parent::__construct();
    }

    public static function buildRouteName(string $entityType): string
    {
        return 'frontend.content-system.' . str_replace('_', '-', $entityType);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw ContentSystemException::routesAlreadyLoaded();
        }

        $routes = new RouteCollection();

        foreach ($this->assignableDefinitions as $definition) {
            $entityType = $definition->getContentLayoutEntityType();
            $pathPrefix = $definition->getContentLayoutPathPrefix();

            $path = '/content' . $pathPrefix . '{' . self::PARAMETER_ENTITY_ID . '}';
            $name = self::buildRouteName($entityType);

            $route = new Route($path);
            $route->setMethods([Request::METHOD_GET]);
            $route->setDefaults([
                PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
                PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
                '_controller' => ContentController::class . '::index',
                self::ATTRIBUTE_PATH_PREFIX => $pathPrefix,
            ]);
            $route->setRequirements([self::PARAMETER_ENTITY_ID => '[a-f0-9]{32}']);

            $routes->add($name, $route);
        }

        $this->isLoaded = true;

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === self::ROUTE_TYPE;
    }
}
