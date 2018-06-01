<?php declare(strict_types=1);

namespace Shopware\Core\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Struct;

use Shopware\Core\System\Language\Struct\LanguageBasicStruct;
use Shopware\Core\System\Configuration\Aggregate\ConfigurationGroupOption\Struct\ConfigurationGroupOptionBasicStruct;

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
