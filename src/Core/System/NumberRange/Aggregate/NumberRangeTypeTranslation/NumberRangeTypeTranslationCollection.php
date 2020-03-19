<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                  add(NumberRangeTypeTranslationEntity $type)
 * @method NumberRangeTypeTranslationEntity[]    getIterator()
 * @method NumberRangeTypeTranslationEntity[]    getElements()
 * @method NumberRangeTypeTranslationEntity|null get(string $key)
 * @method NumberRangeTypeTranslationEntity|null first()
 * @method NumberRangeTypeTranslationEntity|null last()
 */
class NumberRangeTypeTranslationCollection extends EntityCollection
{
    public function getNumberRangeTypeIds(): array
    {
        return $this->fmap(function (NumberRangeTypeTranslationEntity $numberRangeTypeTranslation) {
            return $numberRangeTypeTranslation->getNumberRangeTypeId();
        });
    }

    public function filterByNumberRangeTypeId(string $id): self
    {
        return $this->filter(function (NumberRangeTypeTranslationEntity $numberRangeTypeTranslation) use ($id) {
            return $numberRangeTypeTranslation->getNumberRangeTypeId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (NumberRangeTypeTranslationEntity $numberRangeTypeTranslation) {
            return $numberRangeTypeTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (NumberRangeTypeTranslationEntity $numberRangeTypeTranslation) use ($id) {
            return $numberRangeTypeTranslation->getLanguageId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'number_range_type_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return NumberRangeTypeTranslationEntity::class;
    }
}
