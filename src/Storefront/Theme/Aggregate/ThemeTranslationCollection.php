<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Aggregate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                        add(ThemeTranslationEntity $entity)
 * @method void                        set(string $key, ThemeTranslationEntity $entity)
 * @method ThemeTranslationEntity[]    getIterator()
 * @method ThemeTranslationEntity[]    getElements()
 * @method ThemeTranslationEntity|null get(string $key)
 * @method ThemeTranslationEntity|null first()
 * @method ThemeTranslationEntity|null last()
 */
class ThemeTranslationCollection extends EntityCollection
{
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
