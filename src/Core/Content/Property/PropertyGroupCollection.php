<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
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

    public function sortByConfig(): void
    {
        $collator = $this->createCollator();

        foreach ($this->elements as $group) {
            $options = $group->getOptions();
            if (!$options instanceof PropertyGroupOptionCollection) {
                continue;
            }

            $entities = [];

            $sortingType = $group->getSortingType();

            foreach ($options->getIterator() as $option) {
                $entities[] = $option;
            }

            if ($sortingType === PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC) {
                usort($entities, fn (PropertyGroupOptionEntity $a, PropertyGroupOptionEntity $b): int => $this->compareByName($collator, $a, $b));
            } else {
                usort($entities, function (PropertyGroupOptionEntity $a, PropertyGroupOptionEntity $b) use ($collator): int {
                    $posA = (int) ($a->getTranslation('position') ?? $a->getPosition() ?? 0);
                    $posB = (int) ($b->getTranslation('position') ?? $b->getPosition() ?? 0);

                    if ($posA !== $posB) {
                        return $posA <=> $posB;
                    }

                    return $this->compareByName($collator, $a, $b);
                });
            }

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

    private function createCollator(): \Collator
    {
        $acceptLanguage = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        $locale = \Locale::acceptFromHttp($acceptLanguage);
        if ($locale === false || $locale === '') {
            $locale = \Locale::getDefault() ?: 'en_US';
        }

        $collator = new \Collator($locale);
        $collator->setAttribute(\Collator::NUMERIC_COLLATION, \Collator::ON);
        $collator->setAttribute(\Collator::STRENGTH, \Collator::SECONDARY);

        return $collator;
    }

    private function compareByName(\Collator $collator, PropertyGroupOptionEntity $a, PropertyGroupOptionEntity $b): int
    {
        $nameA = (string) ($a->getTranslation('name') ?? '');
        $nameB = (string) ($b->getTranslation('name') ?? '');

        $result = $collator->compare($nameA, $nameB);
        if ($result !== false && $result !== 0) {
            return $result;
        }

        return strnatcmp($nameA, $nameB);
    }
}
