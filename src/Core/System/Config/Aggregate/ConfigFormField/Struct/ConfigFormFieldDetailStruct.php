<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormField\Struct;


use Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Collection\ConfigFormFieldTranslationBasicCollection;
use Shopware\System\Config\Aggregate\ConfigFormFieldValue\Collection\ConfigFormFieldValueBasicCollection;
use Shopware\System\Config\Struct\ConfigFormBasicStruct;

class ConfigFormFieldDetailStruct extends ConfigFormFieldBasicStruct
{
    /**
     * @var ConfigFormBasicStruct|null
     */
    protected $configForm;

    /**
     * @var ConfigFormFieldTranslationBasicCollection
     */
    protected $translations;

    /**
     * @var ConfigFormFieldValueBasicCollection
     */
    protected $values;

    public function __construct()
    {
        $this->translations = new ConfigFormFieldTranslationBasicCollection();

        $this->values = new ConfigFormFieldValueBasicCollection();
    }

    public function getConfigForm(): ?ConfigFormBasicStruct
    {
        return $this->configForm;
    }

    public function setConfigForm(?ConfigFormBasicStruct $configForm): void
    {
        $this->configForm = $configForm;
    }

    public function getTranslations(): ConfigFormFieldTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(ConfigFormFieldTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getValues(): ConfigFormFieldValueBasicCollection
    {
        return $this->values;
    }

    public function setValues(ConfigFormFieldValueBasicCollection $values): void
    {
        $this->values = $values;
    }
}
