<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;

class ConfigurationGroupTranslationDetailStruct extends ConfigurationGroupTranslationBasicStruct
{
    /**
     * @var ConfigurationGroupBasicStruct
     */
    protected $configurationGroup;

    /**
     * @var ShopBasicStruct
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

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
