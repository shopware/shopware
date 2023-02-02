<?php declare(strict_types=1);

namespace Shopware\Docs\Inspection;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ModuleTagCollection>
 */
class CharacteristicsCollection extends Collection
{
    /**
     * @return list<ModuleTagCollection>
     */
    public function filterTagName(string $tagName): array
    {
        return $this->fmap(static function (ModuleTagCollection $collection) use ($tagName) {
            $filteredTags = $collection->filterName($tagName);

            if ($filteredTags->count() === 0) {
                return false;
            }

            return $filteredTags;
        });
    }

    protected function getExpectedClass(): ?string
    {
        return ModuleTagCollection::class;
    }
}
