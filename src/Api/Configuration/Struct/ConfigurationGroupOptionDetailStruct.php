<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Struct;

use Shopware\Api\Configuration\Struct\ConfigurationGroupBasicStruct;
use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionTranslationBasicCollection;

class ConfigurationGroupOptionDetailStruct extends ConfigurationGroupOptionBasicStruct
{

    /**
     * @var ConfigurationGroupBasicStruct
     */
    protected $configurationGroup;

    /**
     * @var ConfigurationGroupOptionTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {

        $this->translations = new ConfigurationGroupOptionTranslationBasicCollection();

    }


    public function getConfigurationGroup(): ConfigurationGroupBasicStruct
    {
        return $this->configurationGroup;
    }

    public function setConfigurationGroup(ConfigurationGroupBasicStruct $configurationGroup): void
    {
        $this->configurationGroup = $configurationGroup;
    }


    public function getTranslations(): ConfigurationGroupOptionTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(ConfigurationGroupOptionTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }

}