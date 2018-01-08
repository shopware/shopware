<?php declare(strict_types=1);

namespace Shopware\Api\Config\Struct;

use Shopware\Api\Config\Collection\ConfigFormFieldTranslationBasicCollection;
use Shopware\Api\Config\Collection\ConfigFormFieldValueBasicCollection;

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
