<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Struct;

use Shopware\Application\Language\Struct\LanguageBasicStruct;

class ConfigurationGroupTranslationDetailStruct extends ConfigurationGroupTranslationBasicStruct
{
    /**
     * @var ConfigurationGroupBasicStruct
     */
    protected $configurationGroup;

    /**
     * @var LanguageBasicStruct
     */
    protected $language;

    public function getConfigurationGroup(): ConfigurationGroupBasicStruct
    {
        return $this->configurationGroup;
    }

    public function setConfigurationGroup(ConfigurationGroupBasicStruct $configurationGroup): void
    {
        $this->configurationGroup = $configurationGroup;
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
