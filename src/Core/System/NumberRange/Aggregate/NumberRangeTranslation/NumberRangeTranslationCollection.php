<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                              add(NumberRangeTranslationEntity $numberRange)
 * @method NumberRangeTranslationEntity[]    getIterator()
 * @method NumberRangeTranslationEntity[]    getElements()
 * @method NumberRangeTranslationEntity|null get(string $key)
 * @method NumberRangeTranslationEntity|null first()
 * @method NumberRangeTranslationEntity|null last()
 */
class NumberRangeTranslationCollection extends EntityCollection
{
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
