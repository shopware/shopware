<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Configurator;

use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductPageConfiguratorLoader
{
    /**
     * @var EntityRepositoryInterface
     */
    private $configuratorRepository;

    /**
     * @var AvailableCombinationLoader
     */
    private $combinationLoader;

    public function __construct(
        EntityRepositoryInterface $configuratorRepository,
        AvailableCombinationLoader $combinationLoader
    ) {
        $this->combinationLoader = $combinationLoader;
        $this->configuratorRepository = $configuratorRepository;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function load(
        SalesChannelProductEntity $product,
        SalesChannelContext $salesChannelContext
    ): PropertyGroupCollection {
        if (!$product->getParentId()) {
            return new PropertyGroupCollection();
        }

        $groups = $this->loadSettings($product, $salesChannelContext);

        $groups = $this->sortSettings($groups, $product);

        $combinations = $this->combinationLoader->load(
            $product->getParentId(),
            $salesChannelContext->getContext()
        );

        $current = $this->buildCurrentOptions($product, $groups);

        foreach ($groups as $group) {
            $options = $group->getOptions();
            if ($group->getOptions() === null) {
                continue;
            }

            foreach ($options as $option) {
                $combinable = $this->isCombinable($option, $current, $combinations);
                if ($combinable === null) {
                    $group->getOptions()->remove($option->getId());

                    continue;
                }

                $option->setCombinable($combinable);
            }
        }

        return $groups;
    }

    /**
     * @throws InconsistentCriteriaIdsException
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

            if (!$group->getOptions()) {
                $group->setOptions(new PropertyGroupOptionCollection());
            }

            $group->getOptions()->add($option);

            $option->setConfiguratorSetting($setting);
        }

        return $groups;
    }

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
            $group->getOptions()->sort(
                static function (PropertyGroupOptionEntity $a, PropertyGroupOptionEntity $b) use ($group) {
                    if ($a->getConfiguratorSetting()->getPosition() !== $b->getConfiguratorSetting()->getPosition()) {
                        return $a->getConfiguratorSetting()->getPosition() <=> $b->getConfiguratorSetting()->getPosition();
                    }

                    if ($group->getSortingType() === PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC) {
                        return strnatcmp($a->getTranslation('name'), $b->getTranslation('name'));
                    }

                    if ($group->getSortingType() === PropertyGroupDefinition::SORTING_TYPE_NUMERIC) {
                        return $a->getTranslation('name') <=> $b->getTranslation('name');
                    }

                    return $a->getTranslation('position') <=> $b->getTranslation('position');
                }
            );
        }

        $collection = new PropertyGroupCollection($sorted);

        // check if product has an individual sorting configuration for property groups
        $config = $product->getConfiguratorGroupConfig();
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

    private function isCombinable(
        PropertyGroupOptionEntity $option,
        array $current,
        AvailableCombinationResult $combinations
    ): ?bool {
        unset($current[$option->getGroupId()]);
        $current[] = $option->getId();

        // available with all other current selected options
        if ($combinations->hasCombination($current)) {
            return true;
        }

        // available but not with the other current selected options
        if ($combinations->hasOptionId($option->getId())) {
            return false;
        }

        return null;
    }

    private function buildCurrentOptions(SalesChannelProductEntity $product, PropertyGroupCollection $groups): array
    {
        $keyMap = $groups->getOptionIdMap();

        $current = [];
        foreach ($product->getOptionIds() as $optionId) {
            $groupId = $keyMap[$optionId] ?? null;
            if ($groupId === null) {
                continue;
            }

            $current[$groupId] = $optionId;
        }

        return $current;
    }
}
