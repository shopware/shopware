<?php declare(strict_types=1);

namespace Shopware\System\Config\Struct;

use Shopware\System\Locale\Struct\LocaleBasicStruct;

class ConfigFormFieldTranslationDetailStruct extends ConfigFormFieldTranslationBasicStruct
{
    /**
     * @var ConfigFormFieldBasicStruct
     */
    protected $configFormField;

    /**
     * @var LocaleBasicStruct
     */
    protected $locale;

    public function getConfigFormField(): ConfigFormFieldBasicStruct
    {
        return $this->configFormField;
    }

    public function setConfigFormField(ConfigFormFieldBasicStruct $configFormField): void
    {
        $this->configFormField = $configFormField;
    }

    public function getLocale(): LocaleBasicStruct
    {
        return $this->locale;
    }

    public function setLocale(LocaleBasicStruct $locale): void
    {
        $this->locale = $locale;
    }
}
