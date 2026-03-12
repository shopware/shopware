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
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - required parameter $localeCode will be added
     */
    public function sortByConfig(/* string $localeCode */): void
    {
        $localeCode = \func_num_args() === 1 ? func_get_arg(0) : '';

        $collator = $this->createCollator($localeCode);

        foreach ($this->elements as $group) {
            $options = $group->get('options');
            if (!$options instanceof PropertyGroupOptionCollection) {
                continue;
            }

            $elements = $options->getElements();
            $sortingByPosition = $group->get('sortingType') !== PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC;
            $posititionCol = [];
            $nameCol = [];

            foreach ($elements as $element) {
                $name = $element->getTranslation('name') ?? '';
                $nameCol[] = (string) $collator->getSortKey($name);

                if ($sortingByPosition) {
                    $posititionCol[] = (int) ($element->getTranslation('position') ?? $element->get('position') ?? 0);
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
            $sortArgs[] = &$elements;

            array_multisort(...$sortArgs);

            $sortedOptions = new PropertyGroupOptionCollection();
            $sortedOptions->fillOptions($elements);

            $group->assign(['options' => $sortedOptions]);
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

    private function createCollator(string $localeCode): \Collator
    {
        $locale = $localeCode !== '' ? \Locale::canonicalize($localeCode) : '';
        if ($locale === null || $locale === '') {
            $locale = \Locale::getDefault() ?: 'en_US';
        }

        $collator = new \Collator($locale);
        $collator->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);
        $collator->setAttribute(\Collator::ALTERNATE_HANDLING, \Collator::SHIFTED);

        return $collator;
    }
}
