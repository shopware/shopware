<?php declare(strict_types=1);

namespace Shopware\Core\System\Configuration\Aggregate\ConfigurationGroupOption\Struct;

use Shopware\Core\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationBasicCollection;

class ConfigurationGroupOptionDetailStruct extends ConfigurationGroupOptionBasicStruct
{
    /**
     * @var \Shopware\Core\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationBasicCollection
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
