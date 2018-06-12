<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductConfigurator;

use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Collection\ConfigurationGroupDetailCollection;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\Collection\ConfigurationGroupDetailStruct;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionBasicCollection;

class ProductConfiguratorBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductConfiguratorBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ProductConfiguratorBasicStruct
    {
        return parent::current();
    }

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductConfiguratorBasicStruct $productConfigurator) {
            return $productConfigurator->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductConfiguratorBasicStruct $productConfigurator) use ($id) {
            return $productConfigurator->getProductId() === $id;
        });
    }

    public function getOptionIds(): array
    {
        return $this->fmap(function (ProductConfiguratorBasicStruct $productConfigurator) {
            return $productConfigurator->getOptionId();
        });
    }

    public function filterByOptionId(string $id): self
    {
        return $this->filter(function (ProductConfiguratorBasicStruct $productConfigurator) use ($id) {
            return $productConfigurator->getOptionId() === $id;
        });
    }

    public function getOptions(): \Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionBasicCollection
    {
        return new \Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionBasicCollection(
            $this->fmap(function (ProductConfiguratorBasicStruct $productConfigurator) {
                return $productConfigurator->getOption();
            })
        );
    }

    public function getGroupedOptions(): ConfigurationGroupDetailCollection
    {
        $groups = new ConfigurationGroupDetailCollection();
        foreach ($this->elements as $element) {
            if ($groups->has($element->getOption()->getGroupId())) {
                $group = $groups->get($element->getOption()->getGroupId());
            } else {
                $group = ConfigurationGroupDetailStruct::createFrom(
                    $element->getOption()->getGroup()
                );

                $groups->add($group);
            }

            $group->getOptions()->add($element->getOption());
        }

        return $groups;
    }

    public function getByOptionId(string $optionId): ?ProductConfiguratorBasicStruct
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
        $this->sort(function (ProductConfiguratorBasicStruct $a, ProductConfiguratorBasicStruct $b) {
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
        return ProductConfiguratorBasicStruct::class;
    }
}
