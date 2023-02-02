<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ThemeEntity>
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
