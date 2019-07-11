<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void             add(ThemeEntity $entity)
 * @method void             set(string $key, ThemeEntity $entity)
 * @method ThemeEntity[]    getIterator()
 * @method ThemeEntity[]    getElements()
 * @method ThemeEntity|null get(string $key)
 * @method ThemeEntity|null first()
 * @method ThemeEntity|null last()
 */
class ThemeCollection extends EntityCollection
{
    public function getByTechnicalName(string $technicalName): ?ThemeEntity
    {
        return $this->filter(function (ThemeEntity $theme) use ($technicalName) {
            return $theme->getTechnicalName() === $technicalName;
        })->first();
    }

    protected function getExpectedClass(): string
    {
        return ThemeEntity::class;
    }
}
