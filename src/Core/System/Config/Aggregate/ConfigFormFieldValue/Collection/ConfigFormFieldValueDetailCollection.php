<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormFieldValue\Collection;

use Shopware\Core\System\Config\Aggregate\ConfigFormField\Collection\ConfigFormFieldBasicCollection;
use Shopware\Core\System\Config\Aggregate\ConfigFormFieldValue\Struct\ConfigFormFieldValueDetailStruct;

class ConfigFormFieldValueDetailCollection extends ConfigFormFieldValueBasicCollection
{
    /**
     * @var \Shopware\Core\System\Config\Aggregate\ConfigFormFieldValue\Struct\ConfigFormFieldValueDetailStruct[]
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
