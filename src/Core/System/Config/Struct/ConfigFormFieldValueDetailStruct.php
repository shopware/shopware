<?php declare(strict_types=1);

namespace Shopware\System\Config\Struct;

class ConfigFormFieldValueDetailStruct extends ConfigFormFieldValueBasicStruct
{
    /**
     * @var ConfigFormFieldBasicStruct
     */
    protected $configFormField;

    public function getConfigFormField(): ConfigFormFieldBasicStruct
    {
        return $this->configFormField;
    }

    public function setConfigFormField(ConfigFormFieldBasicStruct $configFormField): void
    {
        $this->configFormField = $configFormField;
    }
}
