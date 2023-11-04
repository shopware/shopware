<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingEntity;
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
     */
    public function __construct(
        private readonly EntityRepository $configuratorRepository,
        private readonly AbstractAvailableCombinationLoader $combinationLoader
    ) {
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function load(
        SalesChannelProductEntity $product,
        SalesChannelContext $context
    ): PropertyGroupCollection {
        if (!$product->getParentId()) {
            return new PropertyGroupCollection();
        }

        $groups = $this->loadSettings($product, $context);

        $groups = $this->sortSettings($groups, $product);

        $combinations = $this->combinationLoader->load(
            $product->getParentId(),
            $context->getContext(),
            $context->getSalesChannelId()
        );

        $current = $this->buildCurrentOptions($product, $groups);

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
        }

        return $groups;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     *
     * @return array<string, PropertyGroupEntity>|null
     */
    private function loadSettings(SalesChannelProductEntity $product, SalesChannelContext $context): ?array
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('productId', $product->getParentId() ?? $product->getId())
        );

        $criteria->addAssociation('option.group')
            ->addAssociation('option.media')
            ->addAssociation('media');

        $settings = $this->configuratorRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        if ($settings->count() <= 0) {
            return null;
        }
        $groups = [];

        /** @var ProductConfiguratorSettingEntity $setting */
        foreach ($settings as $setting) {
            $option = $setting->getOption();
            if ($option === null) {
                continue;
            }

            $group = $option->getGroup();
            if ($group === null) {
                continue;
            }

            $groupId = $group->getId();

            if (isset($groups[$groupId])) {
                $group = $groups[$groupId];
            }

            $groups[$groupId] = $group;

            $options = $group->getOptions();
            if ($options === null) {
                $options = new PropertyGroupOptionCollection();
                $group->setOptions($options);
            }
            $options->add($option);

            $options->add($option);

            $option->setConfiguratorSetting($setting);
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

        /** @var PropertyGroupEntity $group */
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

        // check if product has an individual sorting configuration for property groups\
        $config = $product->getVariantListingConfig();
        if ($config) {
            $config = $config->getConfiguratorGroupConfig();
        }

        if (!$config) {
            $collection->sortByPositions();

            return $collection;
        }

        $sortedGroupIds = array_column($config, 'id');

        // ensure all ids are in the array (but only once)
        $sortedGroupIds = array_unique(array_merge($sortedGroupIds, $collection->getIds()));

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
        $keyMap = $groups->getOptionIdMap();

        $optionIds = $product->getOptionIds() ?? [];
        $current = [];

        if ($product->getOptionIds() === null) {
            return $current;
        }

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
