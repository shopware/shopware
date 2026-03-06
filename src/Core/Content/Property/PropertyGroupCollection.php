<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PropertyGroupEntity>
 */
#[Package('inventory')]
class PropertyGroupCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getOptionIdMap(): array
    {
        $map = [];

        foreach ($this->elements as $group) {
            if ($group->getOptions() === null) {
                continue;
            }

            foreach ($group->getOptions() as $option) {
                $map[$option->getId()] = $group->getId();
            }
        }

        return $map;
    }

    public function sortByPositions(): void
    {
        uasort($this->elements, static function (PropertyGroupEntity $a, PropertyGroupEntity $b) {
            $posA = $a->getTranslation('position') ?? $a->getPosition() ?? 0;
            $posB = $b->getTranslation('position') ?? $b->getPosition() ?? 0;
            if ($posA === $posB) {
                return strnatcmp((string) $a->getTranslation('name'), (string) $b->getTranslation('name'));
            }

            return $posA <=> $posB;
        });
    }

    /**
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - parameter $localeCode will be added
     */
    public function sortByConfig(/* ?string $localeCode = null */): void
    {
        $localeCode = \func_num_args() === 1 ? func_get_arg(0) : null;

        $collator = $this->createCollator($localeCode);

        foreach ($this->elements as $group) {
            $options = $group->getOptions();
            if (!$options instanceof PropertyGroupOptionCollection) {
                continue;
            }

            $entities = iterator_to_array($options->getIterator());
            $sortingByPosition = $group->getSortingType() !== PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC;
            $posititionCol = [];
            $nameCol = [];

            foreach ($entities as $option) {
                $name = $option->getTranslation('name') ?? '';
                $nameCol[] = (string) $collator->getSortKey($name);

                if ($sortingByPosition) {
                    $posititionCol[] = (int) ($option->getTranslation('position') ?? $option->getPosition() ?? 0);
                }
            }

            $sortArgs = [];
            if ($sortingByPosition) {
                $sortArgs[] = &$posititionCol;
                $sortArgs[] = \SORT_ASC;
                $sortArgs[] = \SORT_NUMERIC;
            }

            $sortArgs[] = &$nameCol;
            $sortArgs[] = \SORT_ASC;
            $sortArgs[] = \SORT_STRING;
            $sortArgs[] = &$entities;

            array_multisort(...$sortArgs);

            $sortedOptions = new PropertyGroupOptionCollection();
            // Bypass expected class validation for performance optimization
            $sortedOptions->fillOptions($entities);

            $group->setOptions($sortedOptions);
        }
    }

    public function getApiAlias(): string
    {
        return 'product_group_collection';
    }

    protected function getExpectedClass(): string
    {
        return PropertyGroupEntity::class;
    }

    private function createCollator(?string $localeCode = null): \Collator
    {
        $locale = ($localeCode !== null && $localeCode !== '') ? \Locale::canonicalize($localeCode) : null;
        if ($locale === null || $locale === '') {
            $locale = \Locale::getDefault() ?: 'en_US';
        }

        $collator = new \Collator($locale);
        $collator->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);
        $collator->setAttribute(\Collator::ALTERNATE_HANDLING, \Collator::SHIFTED);
        $collator->setAttribute(\Collator::CASE_FIRST, \Collator::UPPER_FIRST);
        $collator->setAttribute(\Collator::STRENGTH, \Collator::TERTIARY);

        return $collator;
    }
}
