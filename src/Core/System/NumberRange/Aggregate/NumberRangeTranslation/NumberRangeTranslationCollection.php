<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NumberRangeTranslationEntity>
 */
class NumberRangeTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getNumberRangeIds(): array
    {
        return $this->fmap(function (NumberRangeTranslationEntity $numberRangeTranslation) {
            return $numberRangeTranslation->getNumberRangeId();
        });
    }

    public function filterByNumberRangeId(string $id): self
    {
        return $this->filter(function (NumberRangeTranslationEntity $numberRangeTranslation) use ($id) {
            return $numberRangeTranslation->getNumberRangeId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(function (NumberRangeTranslationEntity $numberRangeTranslation) {
            return $numberRangeTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (NumberRangeTranslationEntity $numberRangeTranslation) use ($id) {
            return $numberRangeTranslation->getLanguageId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'number_range_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return NumberRangeTranslationEntity::class;
    }
}
