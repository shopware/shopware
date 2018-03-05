<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Configuration\Struct\ConfigurationGroupBasicStruct;


class ConfigurationGroupBasicCollection extends EntityCollection
{
    /**
     * @var ConfigurationGroupBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigurationGroupBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigurationGroupBasicStruct
    {
        return parent::current();
    }


    public function getVersionIds(): array
    {
        return $this->fmap(function(ConfigurationGroupBasicStruct $configurationGroup) {
            return $configurationGroup->getVersionId();
        });
    }

    public function filterByVersionId(string $id): ConfigurationGroupBasicCollection
    {
        return $this->filter(function(ConfigurationGroupBasicStruct $configurationGroup) use ($id) {
            return $configurationGroup->getVersionId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupBasicStruct::class;
    }
}