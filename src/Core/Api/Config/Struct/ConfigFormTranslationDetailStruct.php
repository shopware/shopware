<?php declare(strict_types=1);

namespace Shopware\Api\Config\Struct;

use Shopware\Api\Locale\Struct\LocaleBasicStruct;

class ConfigFormTranslationDetailStruct extends ConfigFormTranslationBasicStruct
{
    /**
     * @var ConfigFormBasicStruct
     */
    protected $configForm;

    /**
     * @var LocaleBasicStruct
     */
    protected $locale;

    public function getConfigForm(): ConfigFormBasicStruct
    {
        return $this->configForm;
    }

    public function setConfigForm(ConfigFormBasicStruct $configForm): void
    {
        $this->configForm = $configForm;
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
