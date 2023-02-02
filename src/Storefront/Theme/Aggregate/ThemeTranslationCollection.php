<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Aggregate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ThemeTranslationEntity>
 */
class ThemeTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getThemeIds(): array
    {
        return $this->fmap(function (ThemeTranslationEntity $themeTranslation) {
            return $themeTranslation->getThemeId();
        });
    }

    public function filterByThemeId(string $id): self
    {
        return $this->filter(function (ThemeTranslationEntity $themeTranslation) use ($id) {
            return $themeTranslation->getThemeId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(function (ThemeTranslationEntity $themeTranslation) {
            return $themeTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ThemeTranslationEntity $themeTranslation) use ($id) {
            return $themeTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ThemeTranslationEntity::class;
    }
}
