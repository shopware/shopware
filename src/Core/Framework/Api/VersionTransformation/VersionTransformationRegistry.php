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

    public function getTransformationIndex(): array
    {
        return $this->transformationIndex;
    }

    public function hasTransformationsForVersionAndRoute(int $version, string $route): bool
    {
        return count($this->getTransformationsForVersionAndRoute($version, $route)) > 0;
    }

    public function getTransformationsForVersionAndRoute(int $version, string $route): array
    {
        return array_reduce(
            $this->getTransformationsForVersion($version),
            function (array $carry, array $item) use ($route) {
                return array_merge($carry, $item[$route] ?? []);
            },
            []
        );
    }

    public function getTransformationsForVersion(int $version): array
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
