<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;

/**
 * @extends Collection<File>
 */
#[Package('framework')]
class FileCollection extends Collection
{
    /**
     * @param array<string> $files
     *
     * @return self
     */
    public static function createFromArray(array $files)
    {
        $collection = new self();
        foreach ($files as $file) {
            $collection->add(new File($file));
        }

        return $collection;
    }

    /**
     * @return array<string>
     */
    public function getFilepaths(): array
    {
        return $this->map(static fn (File $element) => $element->getFilepath());
    }

    /**
     * @return array<string>
     */
    public function getPublicPaths(string $prefix): array
    {
        return array_values(array_filter($this->map(static function (File $element) use ($prefix) {
            // Handle Twig UX components
            if (str_contains($element->getFilepath(), TwigComponentHelper::COMPONENT_DIRECTORY)) {
                // Build path - handle empty assetName (for root namespace components)
                $componentPath = $element->assetName !== null && $element->assetName !== ''
                    ? $element->assetName . '/' . basename($element->getFilepath())
                    : basename($element->getFilepath());

                return $prefix . '/components/' . $componentPath;
            }

            // For non-component files, assetName must be set
            if ($element->assetName === null) {
                return null;
            }

            // removes file with old js structure (before async changes) from collection
            if (!str_ends_with($element->getFilepath(), $element->assetName . '/' . basename($element->getFilepath()))) {
                return null;
            }

            return $prefix . '/' . $element->assetName . '/' . basename($element->getFilepath());
        })));
    }

    /**
     * @return array<string, string>
     */
    public function getResolveMappings(): array
    {
        $resolveMappings = [];

        foreach ($this->elements as $file) {
            if (\count($file->getResolveMapping()) > 0) {
                $resolveMappings[] = $file->getResolveMapping();
            }
        }

        return array_merge(...$resolveMappings);
    }

    protected function getExpectedClass(): ?string
    {
        return File::class;
    }
}
