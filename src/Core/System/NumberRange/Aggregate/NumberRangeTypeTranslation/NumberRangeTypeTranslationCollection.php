<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<NumberRangeTypeTranslationEntity>
 */
#[Package('checkout')]
class NumberRangeTypeTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getNumberRangeTypeIds(): array
    {
        return $this->fmap(fn (NumberRangeTypeTranslationEntity $numberRangeTypeTranslation) => $numberRangeTypeTranslation->getNumberRangeTypeId());
    }

    public function filterByNumberRangeTypeId(string $id): self
    {
        return $this->filter(fn (NumberRangeTypeTranslationEntity $numberRangeTypeTranslation) => $numberRangeTypeTranslation->getNumberRangeTypeId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (NumberRangeTypeTranslationEntity $numberRangeTypeTranslation) => $numberRangeTypeTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (NumberRangeTypeTranslationEntity $numberRangeTypeTranslation) => $numberRangeTypeTranslation->getLanguageId() === $id);
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
