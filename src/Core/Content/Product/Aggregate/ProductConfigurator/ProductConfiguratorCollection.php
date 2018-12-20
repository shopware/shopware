<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductConfigurator;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionCollection;
use Shopware\Core\Content\Configuration\ConfigurationGroupCollection;
use Shopware\Core\Content\Configuration\ConfigurationGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ProductConfiguratorCollection extends EntityCollection
{
    public function getProductIds(): array
    {
        return $this->fmap(function (ProductConfiguratorEntity $productConfigurator) {
            return $productConfigurator->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductConfiguratorEntity $productConfigurator) use ($id) {
            return $productConfigurator->getProductId() === $id;
        });
    }

    public function getOptionIds(): array
    {
        return $this->fmap(function (ProductConfiguratorEntity $productConfigurator) {
            return $productConfigurator->getOptionId();
        });
    }

    public function filterByOptionId(string $id): self
    {
        return $this->filter(function (ProductConfiguratorEntity $productConfigurator) use ($id) {
            return $productConfigurator->getOptionId() === $id;
        });
    }

    public function getOptions(): ConfigurationGroupOptionCollection
    {
        return new ConfigurationGroupOptionCollection(
            $this->fmap(function (ProductConfiguratorEntity $productConfigurator) {
                return $productConfigurator->getOption();
            })
        );
    }

    public function getGroupedOptions(): ConfigurationGroupCollection
    {
        $groups = new ConfigurationGroupCollection();

        /** @var ProductConfiguratorEntity $element */
        foreach ($this->elements as $element) {
            if ($groups->has($element->getOption()->getGroupId())) {
                $group = $groups->get($element->getOption()->getGroupId());
            } else {
                $group = ConfigurationGroupEntity::createFrom(
                    $element->getOption()->getGroup()
                );

                $groups->add($group);

                $group->setOptions(
                    new ConfigurationGroupOptionCollection()
                );
            }

            $group->getOptions()->add($element->getOption());
        }

        return $groups;
    }

    public function getByOptionId(string $optionId): ?ProductConfiguratorEntity
    {
        /** @var ProductConfiguratorEntity $element */
        foreach ($this->elements as $element) {
            if ($element->getOptionId() === $optionId) {
                return $element;
            }
        }

        return null;
    }

    public function sortByGroup(): void
    {
        $this->sort(function (ProductConfiguratorEntity $a, ProductConfiguratorEntity $b) {
            $groupA = $a->getOption()->getGroup();
            $groupB = $b->getOption()->getGroup();

            if ($groupA->getPosition() === $groupB->getPosition()) {
                return $groupA->getName() <=> $groupB->getName();
            }

            return $groupA->getPosition() <=> $groupB->getPosition();
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductConfiguratorEntity::class;
    }
}
