<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption;

use Shopware\Core\Content\Configuration\ConfigurationGroupCollection;
use Shopware\Core\Content\Configuration\ConfigurationGroupStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;

class ConfigurationGroupOptionCollection extends EntityCollection
{
    /**
     * @var ConfigurationGroupOptionStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigurationGroupOptionStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigurationGroupOptionStruct
    {
        return parent::current();
    }

    public function getConfigurationGroupIds(): array
    {
        return $this->fmap(function (ConfigurationGroupOptionStruct $configurationGroupOption) {
            return $configurationGroupOption->getGroupId();
        });
    }

    public function filterByGroupId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupOptionStruct $configurationGroupOption) use ($id) {
            return $configurationGroupOption->getGroupId() === $id;
        });
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (ConfigurationGroupOptionStruct $configurationGroupOption) {
            return $configurationGroupOption->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (ConfigurationGroupOptionStruct $configurationGroupOption) use ($id) {
            return $configurationGroupOption->getMediaId() === $id;
        });
    }

    public function getGroups(): ConfigurationGroupCollection
    {
        return new ConfigurationGroupCollection(
            $this->fmap(function (ConfigurationGroupOptionStruct $configurationGroupOption) {
                return $configurationGroupOption->getGroup();
            })
        );
    }

    public function groupByConfigurationGroups(): ConfigurationGroupCollection
    {
        $groups = new ConfigurationGroupCollection();
        foreach ($this->elements as $element) {
            if ($groups->has($element->getGroupId())) {
                $group = $groups->get($element->getGroupId());
            } else {
                $group = ConfigurationGroupStruct::createFrom($element->getGroup());
                $groups->add($group);

                $group->setOptions(
                    new EntitySearchResult(
                        0,
                        new ConfigurationGroupOptionCollection(),
                        null,
                        new Criteria(),
                        Context::createDefaultContext('')
                    )
                );
            }

            $group->getOptions()->add($element);
        }

        return $groups;
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupOptionStruct::class;
    }
}
