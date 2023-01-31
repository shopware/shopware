<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ThemeEntity>
 */
#[Package('storefront')]
class ThemeCollection extends EntityCollection
{
    public function getByTechnicalName(string $technicalName): ?ThemeEntity
    {
        return $this->filter(fn (ThemeEntity $theme) => $theme->getTechnicalName() === $technicalName)->first();
    }

    protected function getExpectedClass(): string
    {
        return ThemeEntity::class;
    }
}
