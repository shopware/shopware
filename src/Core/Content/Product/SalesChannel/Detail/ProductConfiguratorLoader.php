<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class ProductConfiguratorLoader
{
    /**
     * @internal
     *
     * @param EntityRepository<PropertyGroupOptionCollection> $optionRepository
     */
    public function __construct(
        private readonly AbstractAvailableCombinationLoader $combinationLoader,
        private readonly EntityRepository $optionRepository
    ) {
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function load(
        SalesChannelProductEntity $product,
        SalesChannelContext $context
    ): PropertyGroupCollection {
        $parentId = $product->getParentId();
        if (!$parentId) {
            return new PropertyGroupCollection();
        }

        $combinations = $this->combinationLoader->loadCombinations(
            $parentId,
            $context,
        );

        $groups = $this->loadSettings($parentId, $combinations, $context);

        $groups = $this->sortSettings($groups, $product);

        $current = $this->buildCurrentOptions($product, $groups);
        $emptyGroupIds = [];

        foreach ($groups as $group) {
            $options = $group->getOptions();
            if ($options === null) {
                continue;
            }

            foreach ($options as $option) {
                $combinable = $this->isCombinable($option, $current, $combinations);
                if ($combinable === null) {
                    $options->remove($option->getId());

                    continue;
                }
                $option->setGroup(null);

                $option->setCombinable($combinable);
            }

            if ($options->count() === 0) {
                $emptyGroupIds[] = $group->getId();
            }
        }

        foreach ($emptyGroupIds as $groupId) {
            $groups->remove($groupId);
        }

        return $groups;
    }

    /**
     * Loads the configurator groups from the options of the real variant
     * combinations, enriched with the parent's `product_configurator_setting`
     * rows. This keeps options without a setting row selectable instead of
     * greying out their sibling options.
     *
     * @throws InconsistentCriteriaIdsException
     *
     * @return array<string, PropertyGroupEntity>|null
     */
    private function loadSettings(
        string $parentId,
        AvailableCombinationResult $combinations,
        SalesChannelContext $context
    ): ?array {
        $optionIds = [];
        foreach ($combinations->getCombinations() as $combinationOptionIds) {
            foreach ($combinationOptionIds as $optionId) {
                $optionIds[$optionId] = true;
            }
        }

        if ($optionIds === []) {
            return null;
        }

        $criteria = new Criteria(array_keys($optionIds));
        $criteria->addAssociation('group')
            ->addAssociation('media');
        $criteria->getAssociation('productConfiguratorSettings')
            ->addFilter(new EqualsFilter('productId', $parentId))
            ->addAssociation('media');

        $options = $this->optionRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        $groups = [];
        $hasSettings = false;

        foreach ($options as $option) {
            $group = $option->getGroup();
            if ($group === null) {
                continue;
            }

            $setting = $option->getProductConfiguratorSettings()?->first();
            if ($setting !== null) {
                $option->setConfiguratorSetting($setting);
                $hasSettings = true;
            }

            $groupId = $group->getId();

            if (isset($groups[$groupId])) {
                $group = $groups[$groupId];
            }

            $groups[$groupId] = $group;

            $groupOptions = $group->getOptions();
            if ($groupOptions === null) {
                $groupOptions = new PropertyGroupOptionCollection();
                $group->setOptions($groupOptions);
            }
            $groupOptions->add($option);
        }

        // A product without any configurator setting intentionally renders no
        // configurator on the product detail page, so setting-less options are
        // only surfaced when at least one option of the product is configured.
        if (!$hasSettings) {
            return null;
        }

        return $groups;
    }

    /**
     * @param array<string, PropertyGroupEntity>|null $groups
     */
    private function sortSettings(?array $groups, SalesChannelProductEntity $product): PropertyGroupCollection
    {
        if (!$groups) {
            return new PropertyGroupCollection();
        }

        $sorted = [];
        foreach ($groups as $group) {
            if (!$group) {
                continue;
            }

            if (!$group->getOptions()) {
                $group->setOptions(new PropertyGroupOptionCollection());
            }

            $sorted[$group->getId()] = $group;
        }

        foreach ($sorted as $group) {
            $options = $group->getOptions();
            if ($options === null) {
                continue;
            }
            $options->sort(
                static function (PropertyGroupOptionEntity $a, PropertyGroupOptionEntity $b) use ($group) {
                    $configuratorSettingA = $a->getConfiguratorSetting();
                    $configuratorSettingB = $b->getConfiguratorSetting();

                    if ($configuratorSettingA !== null && $configuratorSettingB !== null
                        && $configuratorSettingA->getPosition() !== $configuratorSettingB->getPosition()) {
                        return $configuratorSettingA->getPosition() <=> $configuratorSettingB->getPosition();
                    }

                    if ($group->getSortingType() === PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC) {
                        return strnatcmp((string) $a->getTranslation('name'), (string) $b->getTranslation('name'));
                    }

                    return ($a->getTranslation('position') ?? $a->getPosition() ?? 0) <=> ($b->getTranslation('position') ?? $b->getPosition() ?? 0);
                }
            );
        }

        $collection = new PropertyGroupCollection($sorted);

        // check if product has an individual sorting configuration for property groups
        $config = $product->getVariantListingConfig()?->getConfiguratorGroupConfig();

        if (!$config) {
            $collection->sortByPositions();

            return $collection;
        }

        $sortedGroupIds = array_column($config, 'id');

        $remainingGroupIds = array_values(array_diff($collection->getIds(), $sortedGroupIds));
        usort(
            $remainingGroupIds,
            static function (string $leftId, string $rightId) use ($collection): int {
                $left = $collection->get($leftId);
                $right = $collection->get($rightId);

                if ($left === null || $right === null) {
                    return 0;
                }

                $leftPosition = $left->getTranslation('position') ?? $left->getPosition() ?? 0;
                $rightPosition = $right->getTranslation('position') ?? $right->getPosition() ?? 0;

                if ($leftPosition !== $rightPosition) {
                    return $leftPosition <=> $rightPosition;
                }

                return strnatcmp((string) $left->getTranslation('name'), (string) $right->getTranslation('name'));
            }
        );

        // ensure all ids are in the array (but only once)
        $sortedGroupIds = array_unique(array_merge($sortedGroupIds, $remainingGroupIds));

        $collection->sortByIdArray($sortedGroupIds);

        return $collection;
    }

    /**
     * @param array<string> $current
     */
    private function isCombinable(
        PropertyGroupOptionEntity $option,
        array $current,
        AvailableCombinationResult $combinations
    ): ?bool {
        unset($current[$option->getGroupId()]);
        $current[] = $option->getId();

        // available with all other current selected options
        if ($combinations->hasCombination($current) && $combinations->isAvailable($current)) {
            return true;
        }

        // available but not with the other current selected options
        if ($combinations->hasOptionId($option->getId())) {
            return false;
        }

        return null;
    }

    /**
     * @return array<int|string, string>
     */
    private function buildCurrentOptions(SalesChannelProductEntity $product, PropertyGroupCollection $groups): array
    {
        $optionIds = $product->getOptionIds();
        if ($optionIds === null || $optionIds === []) {
            return [];
        }

        $keyMap = $groups->getOptionIdMap();

        $current = [];

        foreach ($optionIds as $optionId) {
            $groupId = $keyMap[$optionId] ?? null;
            if ($groupId === null) {
                continue;
            }

            $current[$groupId] = $optionId;
        }

        return $current;
    }
}
