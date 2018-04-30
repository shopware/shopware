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

    public function get(string $id): ? ConfigFormFieldBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigFormFieldBasicStruct
    {
        return parent::current();
    }

    public function getConfigFormIds(): array
    {
        return $this->fmap(function (ConfigFormFieldBasicStruct $configFormField) {
            return $configFormField->getConfigFormId();
        });
    }

    public function filterByConfigFormId(string $id): self
    {
        return $this->filter(function (ConfigFormFieldBasicStruct $configFormField) use ($id) {
            return $configFormField->getConfigFormId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldBasicStruct::class;
    }
}
