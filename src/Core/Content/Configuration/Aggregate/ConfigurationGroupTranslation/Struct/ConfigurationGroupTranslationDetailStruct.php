<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Struct;

use Shopware\Core\Content\Configuration\Struct\ConfigurationGroupBasicStruct;
use Shopware\Core\System\Language\Struct\LanguageBasicStruct;

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
