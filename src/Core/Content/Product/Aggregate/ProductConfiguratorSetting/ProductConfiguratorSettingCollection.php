<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ProductConfiguratorSettingEntity>
 *
 * @package inventory
 */
class ProductConfiguratorSettingCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getProductIds(): array
    {
        return $this->fmap(function (ProductConfiguratorSettingEntity $productConfigurator) {
            return $productConfigurator->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductConfiguratorSettingEntity $productConfigurator) use ($id) {
            return $productConfigurator->getProductId() === $id;
        });
    }

    /**
     * @return list<string>
     */
    public function getOptionIds(): array
    {
        return $this->fmap(function (ProductConfiguratorSettingEntity $productConfigurator) {
            return $productConfigurator->getOptionId();
        });
    }

    public function filterByOptionId(string $id): self
    {
        return $this->filter(function (ProductConfiguratorSettingEntity $productConfigurator) use ($id) {
            return $productConfigurator->getOptionId() === $id;
        });
    }

    public function getOptions(): PropertyGroupOptionCollection
    {
        return new PropertyGroupOptionCollection(
            $this->fmap(function (ProductConfiguratorSettingEntity $productConfigurator) {
                return $productConfigurator->getOption();
            })
        );
    }

    public function getGroupedOptions(): PropertyGroupCollection
    {
        $groups = new PropertyGroupCollection();

        foreach ($this->getIterator() as $element) {
            if ($groups->has($element->getOption()->getGroupId())) {
                $group = $groups->get($element->getOption()->getGroupId());
            } else {
                $group = PropertyGroupEntity::createFrom(
                    $element->getOption()->getGroup()
                );

                $groups->add($group);

                $group->setOptions(
                    new PropertyGroupOptionCollection()
                );
            }

            $group->getOptions()->add($element->getOption());
        }

        return $groups;
    }

    public function getByOptionId(string $optionId): ?ProductConfiguratorSettingEntity
    {
        foreach ($this->getIterator() as $element) {
            if ($element->getOptionId() === $optionId) {
                return $element;
            }
        }

        return null;
    }

    public function getApiAlias(): string
    {
        return 'product_configurator_settings_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductConfiguratorSettingEntity::class;
    }
}
