<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Aggregate\NumberRangeTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<NumberRangeTranslationEntity>
 */
#[Package('checkout')]
class NumberRangeTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getNumberRangeIds(): array
    {
        return $this->fmap(fn (NumberRangeTranslationEntity $numberRangeTranslation) => $numberRangeTranslation->getNumberRangeId());
    }

    public function filterByNumberRangeId(string $id): self
    {
        return $this->filter(fn (NumberRangeTranslationEntity $numberRangeTranslation) => $numberRangeTranslation->getNumberRangeId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (NumberRangeTranslationEntity $numberRangeTranslation) => $numberRangeTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (NumberRangeTranslationEntity $numberRangeTranslation) => $numberRangeTranslation->getLanguageId() === $id);
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
