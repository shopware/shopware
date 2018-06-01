<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormFieldValue\Struct;

use Shopware\Core\System\Config\Aggregate\ConfigFormField\Struct\ConfigFormFieldBasicStruct;

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
