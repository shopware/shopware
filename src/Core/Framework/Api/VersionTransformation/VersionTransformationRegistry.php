<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\VersionTransformation;

class VersionTransformationRegistry
{
    /**
     * @var array
     */
    private $transformationIndex = [];

    public function getTransformationIndex(): array
    {
        return $this->transformationIndex;
    }

    public function buildTransformationIndex(array $transformationClasses): void
    {
        $this->transformationIndex = [];
        foreach ($transformationClasses as $transformationClass) {
            $version = $transformationClass::getVersion();
            $this->transformationIndex[$version] = $this->transformationIndex[$version] ?? [];

            $controllerAction = $transformationClass::getControllerAction();
            $this->transformationIndex[$version][$controllerAction] = $transformationClass;
        }
    }

    public function hasTransformationsForVersion(string $version): bool
    {
        return count($this->getTransformationsForVersion($version)) > 0;
    }

    public function getTransformationsForVersion(string $version): array
    {
        return [];
    }
}
