<?php declare(strict_types=1);

namespace Shopware\System\Config\Collection;

use Shopware\System\Config\Struct\ConfigFormFieldValueBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

class ConfigFormFieldValueBasicCollection extends EntityCollection
{
    /**
     * @var ConfigFormFieldValueBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigFormFieldValueBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigFormFieldValueBasicStruct
    {
        return parent::current();
    }

    public function getConfigFormFieldIds(): array
    {
        return $this->fmap(function (ConfigFormFieldValueBasicStruct $configFormFieldValue) {
            return $configFormFieldValue->getConfigFormFieldId();
        });
    }

    public function filterByConfigFormFieldId(string $id): self
    {
        return $this->filter(function (ConfigFormFieldValueBasicStruct $configFormFieldValue) use ($id) {
            return $configFormFieldValue->getConfigFormFieldId() === $id;
        });
    }

    public function getShopIds(): array
    {
        return $this->fmap(function (ConfigFormFieldValueBasicStruct $configFormFieldValue) {
            return $configFormFieldValue->getShopId();
        });
    }

    public function filterByShopId(string $id): self
    {
        return $this->filter(function (ConfigFormFieldValueBasicStruct $configFormFieldValue) use ($id) {
            return $configFormFieldValue->getShopId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldValueBasicStruct::class;
    }
}
