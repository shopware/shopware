<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Routing\Loader\AnnotationClassLoader;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\Loader\AnnotationFileLoader;
use Symfony\Component\Routing\RouteCollection;

class TestRouteLoader extends AnnotationFileLoader
{
    private AnnotationDirectoryLoader $decorated;

    private RouteCollection $routeCollectionLast;

    private $resources = [];

    public function __construct(AnnotationDirectoryLoader $decorated, FileLocatorInterface $locator, AnnotationClassLoader $loader)
    {
        parent::__construct($locator, $loader);
        $this->decorated = $decorated;
        $this->routeCollectionLast = new RouteCollection();
    }

    public function load($resource, ?string $type = null)
    {
        if (\in_array($resource, $this->resources, true)) {
            return null;
        }
        $this->resources[] = $resource;
        $routeCollection = $this->decorated->load($resource, $type);
        if ($routeCollection === null) {
            return null;
        }

        if ($this->routeCollectionLast->count() <= 0) {
            $this->routeCollectionLast->addCollection($routeCollection);

            return $routeCollection;
        }

        foreach ($routeCollection as $route) {
            foreach ($this->routeCollectionLast as $lastRoute) {
                if (
                    $lastRoute
                    && $lastRoute->getPath() === $route->getPath()
                    && \count(array_intersect($lastRoute->getMethods(), $route->getMethods())) > 0
                ) {
                    if (($parentDefaults = $lastRoute->getOption('parentDefaults')) !== null) {
                        $route->setOptions(
                            array_merge_recursive(
                                $route->getOptions(),
                                array_merge_recursive(
                                    ['parentDefaults' => $parentDefaults],
                                    ['parentDefaults' => [$lastRoute->getDefaults()]]
                                )
                            )
                        );
                    } else {
                        $route->addOptions(
                            ['parentDefaults' => [$lastRoute->getDefaults()]]
                        );
                    }
                }
            }
        }

        $this->routeCollectionLast->addCollection($routeCollection);

        return $routeCollection;
    }
}
