<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Struct;

use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionBasicCollection;
use Shopware\Api\Configuration\Collection\ConfigurationGroupTranslationBasicCollection;

class ConfigurationGroupDetailStruct extends ConfigurationGroupBasicStruct
{
    /**
     * @var ConfigurationGroupOptionBasicCollection
     */
    protected $options;

    /**
     * @var ConfigurationGroupTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->options = new ConfigurationGroupOptionBasicCollection();

        $this->translations = new ConfigurationGroupTranslationBasicCollection();
    }

    public function getOptions(): ConfigurationGroupOptionBasicCollection
    {
        return $this->options;
    }

    public function setOptions(ConfigurationGroupOptionBasicCollection $options): void
    {
        $this->options = $options;
    }

    public function getTranslations(): ConfigurationGroupTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(ConfigurationGroupTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
