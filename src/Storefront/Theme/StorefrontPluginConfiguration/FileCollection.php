<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @method void      add(File $entity)
 * @method void      set(string $key, File $entity)
 * @method File[]    getIterator()
 * @method File[]    getElements()
 * @method File|null get(string $key)
 * @method File|null first()
 * @method File|null last()
 */
class FileCollection extends Collection
{
    public static function createFromArray(array $files)
    {
        $collection = new self();
        foreach ($files as $file) {
            $collection->add(new File($file));
        }

        return $collection;
    }

    public function getFilepaths(): array
    {
        return $this->map(function (File $element) {
            return $element->getFilepath();
        });
    }

    public function getResolveMappings(): array
    {
        $resolveMappings = [];
        /** @var File $file */
        foreach ($this->elements as $file) {
            if (\count($file->getResolveMapping()) > 0) {
                $resolveMappings = array_merge($resolveMappings, $file->getResolveMapping());
            }
        }

        return $resolveMappings;
    }

    protected function getExpectedClass(): ?string
    {
        return File::class;
    }
}
