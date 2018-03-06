<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Struct;

use Shopware\Api\Configuration\Collection\ConfigurationGroupOptionTranslationBasicCollection;

class ConfigurationGroupOptionDetailStruct extends ConfigurationGroupOptionBasicStruct
{
    /**
     * @var ConfigurationGroupOptionTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new ConfigurationGroupOptionTranslationBasicCollection();
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
