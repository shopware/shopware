<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<File>
 */
#[Package('storefront')]
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
        return $this->map(fn (File $element) => $element->getFilepath());
    }

    /**
     * @return array<string>
     */
    public function getPublicPaths(string $prefix): array
    {
        return array_values(array_filter($this->map(function (File $element) use ($prefix) {
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
