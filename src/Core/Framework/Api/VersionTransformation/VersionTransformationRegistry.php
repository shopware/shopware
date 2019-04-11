<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\VersionTransformation;

class VersionTransformationRegistry
{
    /**
     * @var array
     */
    private $transformationIndex = [];

    public function __construct(iterable $transformations)
    {
        $this->buildTransformationIndex($transformations);
    }

    /**
     * Returns all API version transformations that should be allied for requests of `$version` to the passed `$route`
     * in the order of application.
     *
     * @return ApiVersionTransformation[]
     */
    public function getRequestTransformationsForVersionAndRoute(int $version, string $route): array
    {
        return array_reduce(
            $this->getTransformationsForVersion($version),
            function (array $carry, array $item) use ($route) {
                return array_merge($carry, $item[$route] ?? []);
            },
            []
        );
    }

    /**
     * Returns all API version transformations that should be allied for responses to requests of `$version` to the
     * passed `$route` in the order of application.
     *
     * The returned transformations are the same as the ones returned by
     * {@link self::getRequestTransformationsForVersionAndRoute()}, but in reverse order.
     *
     * @return ApiVersionTransformation[]
     */
    public function getResponseTransformationsForVersionAndRoute(int $version, string $route): array
    {
        return array_reverse($this->getRequestTransformationsForVersionAndRoute($version, $route));
    }

    private function getTransformationsForVersion(int $version): array
    {
        return array_filter(
            $this->transformationIndex,
            function (string $transformationVersion) use ($version) {
                return $transformationVersion >= $version;
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    private function buildTransformationIndex(iterable $transformations): void
    {
        $this->transformationIndex = [];
        foreach ($transformations as $transformation) {
            $version = $transformation->getVersion();
            $this->transformationIndex[$version] = $this->transformationIndex[$version] ?? [];

            $route = $transformation->getRoute();
            $this->transformationIndex[$version][$route] = $this->transformationIndex[$version][$route] ?? [];
            $this->transformationIndex[$version][$route][] = $transformation;
        }
        ksort($this->transformationIndex);
    }
}
