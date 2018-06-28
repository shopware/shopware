<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductConfigurator;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionCollection;
use Shopware\Core\Content\Configuration\ConfigurationGroupCollection;
use Shopware\Core\Content\Configuration\ConfigurationGroupStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

class ProductConfiguratorCollection extends EntityCollection
{
    /**
     * @var ProductConfiguratorStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductConfiguratorStruct
    {
        return parent::get($id);
    }

    public function current(): ProductConfiguratorStruct
    {
        return parent::current();
    }

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductConfiguratorStruct $productConfigurator) {
            return $productConfigurator->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductConfiguratorStruct $productConfigurator) use ($id) {
            return $productConfigurator->getProductId() === $id;
        });
    }

    public function getOptionIds(): array
    {
        return $this->fmap(function (ProductConfiguratorStruct $productConfigurator) {
            return $productConfigurator->getOptionId();
        });
    }

    public function filterByOptionId(string $id): self
    {
        return $this->filter(function (ProductConfiguratorStruct $productConfigurator) use ($id) {
            return $productConfigurator->getOptionId() === $id;
        });
    }

    public function getOptions(): ConfigurationGroupOptionCollection
    {
        return new ConfigurationGroupOptionCollection(
            $this->fmap(function (ProductConfiguratorStruct $productConfigurator) {
                return $productConfigurator->getOption();
            })
        );
    }

    public function getGroupedOptions(): ConfigurationGroupCollection
    {
        $groups = new ConfigurationGroupCollection();
        foreach ($this->elements as $element) {
            if ($groups->has($element->getOption()->getGroupId())) {
                $group = $groups->get($element->getOption()->getGroupId());
            } else {
                $group = ConfigurationGroupStruct::createFrom(
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

    public function getByOptionId(string $optionId): ?ProductConfiguratorStruct
    {
        foreach ($this->elements as $element) {
            if ($element->getOptionId() === $optionId) {
                return $element;
            }
        }

        return null;
    }

    public function sortByGroup(): void
    {
        $this->sort(function (ProductConfiguratorStruct $a, ProductConfiguratorStruct $b) {
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
        return ProductConfiguratorStruct::class;
    }
}
