<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormFieldValue;

use Shopware\Core\Framework\ORM\EntityCollection;

class ConfigFormFieldValueCollection extends EntityCollection
{
    /**
     * @var ConfigFormFieldValueStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigFormFieldValueStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigFormFieldValueStruct
    {
        return parent::current();
    }

    public function getConfigFormFieldIds(): array
    {
        return $this->fmap(function (ConfigFormFieldValueStruct $configFormFieldValue) {
            return $configFormFieldValue->getConfigFormFieldId();
        });
    }

    public function filterByConfigFormFieldId(string $id): self
    {
        return $this->filter(function (ConfigFormFieldValueStruct $configFormFieldValue) use ($id) {
            return $configFormFieldValue->getConfigFormFieldId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldValueStruct::class;
    }
}
