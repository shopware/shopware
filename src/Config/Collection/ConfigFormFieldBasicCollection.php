<?php declare(strict_types=1);

namespace Shopware\Config\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Config\Struct\ConfigFormFieldBasicStruct;

class ConfigFormFieldBasicCollection extends EntityCollection
{
    /**
     * @var ConfigFormFieldBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ConfigFormFieldBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ConfigFormFieldBasicStruct
    {
        return parent::current();
    }

    public function getConfigFormUuids(): array
    {
        return $this->fmap(function (ConfigFormFieldBasicStruct $configFormField) {
            return $configFormField->getConfigFormUuid();
        });
    }

    public function filterByConfigFormUuid(string $uuid): ConfigFormFieldBasicCollection
    {
        return $this->filter(function (ConfigFormFieldBasicStruct $configFormField) use ($uuid) {
            return $configFormField->getConfigFormUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldBasicStruct::class;
    }
}
