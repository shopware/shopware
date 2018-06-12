<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormField;

use Shopware\Core\Framework\ORM\EntityCollection;


class ConfigFormFieldCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\System\Config\Aggregate\ConfigFormField\ConfigFormFieldStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigFormFieldStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigFormFieldStruct
    {
        return parent::current();
    }

    public function getConfigFormIds(): array
    {
        return $this->fmap(function (ConfigFormFieldStruct $configFormField) {
            return $configFormField->getConfigFormId();
        });
    }

    public function filterByConfigFormId(string $id): self
    {
        return $this->filter(function (ConfigFormFieldStruct $configFormField) use ($id) {
            return $configFormField->getConfigFormId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldStruct::class;
    }
}
