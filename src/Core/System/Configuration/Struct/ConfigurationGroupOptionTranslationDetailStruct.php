<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;

class ConfigurationGroupOptionTranslationDetailStruct extends ConfigurationGroupOptionTranslationBasicStruct
{
    /**
     * @var ConfigurationGroupOptionBasicStruct
     */
    protected $configurationGroupOption;

    /**
     * @var ShopBasicStruct
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

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
