<?php declare(strict_types=1);

namespace Shopware\System\Config\Collection;

use Shopware\System\Config\Struct\ConfigFormFieldValueDetailStruct;

class ConfigFormFieldValueDetailCollection extends ConfigFormFieldValueBasicCollection
{
    /**
     * @var ConfigFormFieldValueDetailStruct[]
     */
    protected $elements = [];

    public function getConfigFormFields(): ConfigFormFieldBasicCollection
    {
        return new ConfigFormFieldBasicCollection(
            $this->fmap(function (ConfigFormFieldValueDetailStruct $configFormFieldValue) {
                return $configFormFieldValue->getConfigFormField();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldValueDetailStruct::class;
    }
}
