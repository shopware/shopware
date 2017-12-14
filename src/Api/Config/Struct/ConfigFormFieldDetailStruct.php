<?php declare(strict_types=1);

namespace Shopware\Api\Config\Struct;

use Shopware\Api\Config\Collection\ConfigFormFieldTranslationBasicCollection;

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

    public function __construct()
    {
        $this->translations = new ConfigFormFieldTranslationBasicCollection();
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
}
