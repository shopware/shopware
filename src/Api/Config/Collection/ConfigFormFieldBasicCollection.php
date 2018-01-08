<?php declare(strict_types=1);

namespace Shopware\Api\Config\Collection;

use Shopware\Api\Config\Struct\ConfigFormFieldBasicStruct;
use Shopware\Api\Entity\EntityCollection;

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

    public function filterByConfigFormUuid(string $uuid): self
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
