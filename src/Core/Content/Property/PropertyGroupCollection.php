<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Feature;
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
     * @deprecated tag:v6.8.0 - The method will require a locale code parameter in v6.8.0.0.
     */
    public function sortByConfig(/* string $localeCode = 'en_GB' */): void
    {
        $localeCode = \func_num_args() === 1 ? func_get_arg(0) : 'en_GB';
        if ($localeCode === null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.8.0.0', 'sortByConfig(string $localeCode)'));
        }

        $collator = $this->createCollator($localeCode ?? 'en_GB');

        foreach ($this->elements as $group) {
            $options = $group->getOptions();
            if ($options === null) {
                continue;
            }

            $elements = $options->getElements();
            $sortingByPosition = $group->getSortingType() !== PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC;
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
            $sortedOptions->fill($elements);

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

    private function createCollator(string $localeCode): \Collator
    {
        $locale = $localeCode !== '' ? \Locale::canonicalize($localeCode) : '';
        if ($locale === null || $locale === '') {
            $locale = \Locale::getDefault() ?: 'en_GB';
        }

        $collator = new \Collator($locale);
        if (intl_is_failure(intl_get_error_code())) {
            $collator = new \Collator('en_GB');
        }

        $collator->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);
        $collator->setAttribute(\Collator::ALTERNATE_HANDLING, \Collator::SHIFTED);

        return $collator;
    }
}
