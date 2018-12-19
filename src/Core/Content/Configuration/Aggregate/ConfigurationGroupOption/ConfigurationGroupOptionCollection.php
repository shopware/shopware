<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption;

use Shopware\Core\Content\Configuration\ConfigurationGroupCollection;
use Shopware\Core\Content\Configuration\ConfigurationGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ConfigurationGroupOptionCollection extends EntityCollection
{
    public function getConfigurationGroupIds(): array
    {
        return $this->fmap(function (ConfigurationGroupOptionEntity $configurationGroupOption) {
            return $configurationGroupOption->getGroupId();
        });
    }

    public function filterByGroupId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupOptionEntity $configurationGroupOption) use ($id) {
            return $configurationGroupOption->getGroupId() === $id;
        });
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (ConfigurationGroupOptionEntity $configurationGroupOption) {
            return $configurationGroupOption->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupOptionEntity $configurationGroupOption) use ($id) {
            return $configurationGroupOption->getMediaId() === $id;
        });
    }

    public function getGroups(): ConfigurationGroupCollection
    {
        return new ConfigurationGroupCollection(
            $this->fmap(function (ConfigurationGroupOptionEntity $configurationGroupOption) {
                return $configurationGroupOption->getGroup();
            })
        );
    }

    public function groupByConfigurationGroups(): ConfigurationGroupCollection
    {
        $groups = new ConfigurationGroupCollection();

        /** @var ConfigurationGroupOptionEntity $element */
        foreach ($this->elements as $element) {
            if ($groups->has($element->getGroupId())) {
                $group = $groups->get($element->getGroupId());
            } else {
                $group = ConfigurationGroupEntity::createFrom($element->getGroup());
                $groups->add($group);

                $group->setOptions(new self());
            }

            $group->getOptions()->add($element);
        }

        return $groups;
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupOptionEntity::class;
    }
}
