<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Struct;

use Shopware\Application\Language\Struct\LanguageBasicStruct;

class ConfigurationGroupOptionTranslationDetailStruct extends ConfigurationGroupOptionTranslationBasicStruct
{
    /**
     * @var ConfigurationGroupOptionBasicStruct
     */
    protected $configurationGroupOption;

    /**
     * @var LanguageBasicStruct
     */
    protected $language;

    public function getConfigurationGroupOption(): ConfigurationGroupOptionBasicStruct
    {
        return $this->configurationGroupOption;
    }

    public function setConfigurationGroupOption(ConfigurationGroupOptionBasicStruct $configurationGroupOption): void
    {
        $this->configurationGroupOption = $configurationGroupOption;
    }

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }
}
