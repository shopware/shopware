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

    public function hasTransformationsForVersionAndAction(int $version, string $action): bool
    {
        return count($this->getTransformationsForVersionAndAction($version, $action)) > 0;
    }

    public function getTransformationsForVersionAndAction(int $version, string $action): array
    {
        return array_reduce(
            $this->getTransformationsForVersion($version),
            function (array $carry, array $item) use ($action) {
                return array_merge($carry, $item[$action] ?? []);
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

            $controllerAction = $transformation->getControllerAction();
            $this->transformationIndex[$version][$controllerAction] = $this->transformationIndex[$version][$controllerAction] ?? [];
            $this->transformationIndex[$version][$controllerAction][] = $transformation;
        }
        ksort($this->transformationIndex);
    }
}
