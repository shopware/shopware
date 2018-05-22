<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormFieldValue\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\Config\Aggregate\ConfigFormFieldValue\Struct\ConfigFormFieldValueBasicStruct;

class ConfigFormFieldValueBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\System\Config\Aggregate\ConfigFormFieldValue\Struct\ConfigFormFieldValueBasicStruct[]
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

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldValueBasicStruct::class;
    }
}
