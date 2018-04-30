<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Configuration\Collection\ConfigurationGroupDetailCollection;
use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionBasicCollection;
use Shopware\Api\Configuration\Struct\ConfigurationGroupDetailStruct;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Product\Struct\ProductConfiguratorBasicStruct;

class ProductConfiguratorBasicCollection extends EntityCollection
{
    /**
     * @var ProductConfiguratorBasicStruct[]
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

    public function getOptions(): ConfigurationGroupOptionBasicCollection
    {
        return new ConfigurationGroupOptionBasicCollection(
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
            $a = $a->getOption()->getGroup();
            $b = $b->getOption()->getGroup();

            if ($a->getPosition() === $b->getPosition()) {
                return $a->getName() <=> $b->getName();
            }

            return $a->getPosition() <=> $b->getPosition();
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductConfiguratorBasicStruct::class;
    }
}
