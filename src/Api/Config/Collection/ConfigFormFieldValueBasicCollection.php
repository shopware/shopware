<?php declare(strict_types=1);

namespace Shopware\Api\Config\Collection;

use Shopware\Api\Config\Struct\ConfigFormFieldValueBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class ConfigFormFieldValueBasicCollection extends EntityCollection
{
    /**
     * @var ConfigFormFieldValueBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ConfigFormFieldValueBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ConfigFormFieldValueBasicStruct
    {
        return parent::current();
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (ConfigFormFieldValueBasicStruct $configFormFieldValue) {
            return $configFormFieldValue->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): ConfigFormFieldValueBasicCollection
    {
        return $this->filter(function (ConfigFormFieldValueBasicStruct $configFormFieldValue) use ($uuid) {
            return $configFormFieldValue->getShopUuid() === $uuid;
        });
    }

    public function getConfigFormFieldUuids(): array
    {
        return $this->fmap(function (ConfigFormFieldValueBasicStruct $configFormFieldValue) {
            return $configFormFieldValue->getConfigFormFieldUuid();
        });
    }

    public function filterByConfigFormFieldUuid(string $uuid): ConfigFormFieldValueBasicCollection
    {
        return $this->filter(function (ConfigFormFieldValueBasicStruct $configFormFieldValue) use ($uuid) {
            return $configFormFieldValue->getConfigFormFieldUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldValueBasicStruct::class;
    }
}
