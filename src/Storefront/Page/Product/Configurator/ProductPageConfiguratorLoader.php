<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Configurator;

use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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

    public function load(SalesChannelProductEntity $product, SalesChannelContext $context): PropertyGroupCollection
    {
        if (!$product->getParentId()) {
            return new PropertyGroupCollection();
        }

        $groups = $this->loadSettings($product, $context);

        $groups = $this->sortSettings($product, $groups);

        $combinations = $this->combinationLoader->load(
            $product->getParentId() ?? $product->getId(),
            $context->getContext()
        );

        $current = $this->buildCurrentOptions($product, $groups);

        /** @var PropertyGroupEntity $group */
        foreach ($groups as $group) {
            if (!$group->getOptions()) {
                continue;
            }

            foreach ($group->getOptions() as $option) {
                try {
                    $option->setCombinable(
                        $this->isCombinable($option, $current, $combinations)
                    );
                } catch (\Exception $e) {
                    $group->getOptions()->remove($option->getId());
                }
            }
        }

        return $groups;
    }

    private function loadSettings(SalesChannelProductEntity $product, SalesChannelContext $context): ?array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('product_configurator_setting.productId', $product->getParentId() ?? $product->getId())
        );

        $nested = new Criteria();
        $nested->addAssociation('property_group_option.group');

        $criteria->addAssociation('product_configurator_setting.option', $nested);

        $settings = $this->configuratorRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        if ($settings->count() <= 0) {
            return null;
        }
        $groups = [];

        /** @var ProductConfiguratorSettingEntity $setting */
        foreach ($settings as $setting) {
            $group = $setting->getOption()->getGroup();

            if (isset($groups[$group->getId()])) {
                $group = $groups[$group->getId()];
            }

            $groups[$group->getId()] = $group;

            if (!$group->getOptions()) {
                $group->setOptions(new PropertyGroupOptionCollection());
            }

            $group->getOptions()->add($setting->getOption());

            $setting->getOption()->setConfiguratorSetting($setting);
        }

        return $groups;
    }

    private function sortSettings(SalesChannelProductEntity $product, array $groups): PropertyGroupCollection
    {
        $sorting = $product->getConfiguratorGroupSorting() ?? [];

        $sorted = [];

        foreach ($sorting as $groupId) {
            if (!isset($groups[$groupId])) {
                continue;
            }
            $sorted[$groupId] = $groups[$groupId];
        }

        foreach ($groups as $groupId => $group) {
            if (isset($sorted[$groupId])) {
                continue;
            }
            $sorted[$groupId] = $group;
        }

        foreach ($groups as $group) {
            if (!$group->getOptions()) {
                continue;
            }

            /* @var PropertyGroupEntity $group */
            $group->getOptions()->sort(
                function (PropertyGroupOptionEntity $a, PropertyGroupOptionEntity $b) {
                    return $a->getConfiguratorSetting()->getPosition() <=> $b->getConfiguratorSetting()->getPosition();
                }
            );
        }

        return new PropertyGroupCollection($sorted);
    }

    private function isCombinable(PropertyGroupOptionEntity $option, array $current, AvailableCombinationResult $combinations): bool
    {
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

        // not buyable - out of stock
        throw new \Exception();
    }

    private function buildCurrentOptions(SalesChannelProductEntity $product, PropertyGroupCollection $groups): ?array
    {
        $keyMap = $groups->getOptionIdMap();

        $current = [];
        foreach ($product->getOptionIds() as $optionId) {
            $groupId = $keyMap[$optionId];

            $current[$groupId] = $optionId;
        }

        return $current;
    }
}
