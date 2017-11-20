<?php declare(strict_types=1);

namespace Shopware\Config\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Config\Struct\ConfigFormBasicStruct;

class ConfigFormBasicCollection extends EntityCollection
{
    /**
     * @var ConfigFormBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ConfigFormBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ConfigFormBasicStruct
    {
        return parent::current();
    }

    public function getParentUuids(): array
    {
        return $this->fmap(function (ConfigFormBasicStruct $configForm) {
            return $configForm->getParentUuid();
        });
    }

    public function filterByParentUuid(string $uuid): ConfigFormBasicCollection
    {
        return $this->filter(function (ConfigFormBasicStruct $configForm) use ($uuid) {
            return $configForm->getParentUuid() === $uuid;
        });
    }

    public function getPluginUuids(): array
    {
        return $this->fmap(function (ConfigFormBasicStruct $configForm) {
            return $configForm->getPluginUuid();
        });
    }

    public function filterByPluginUuid(string $uuid): ConfigFormBasicCollection
    {
        return $this->filter(function (ConfigFormBasicStruct $configForm) use ($uuid) {
            return $configForm->getPluginUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormBasicStruct::class;
    }
}
