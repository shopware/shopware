<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\Struct;

use Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\Struct\ConfigurationGroupOptionBasicStruct;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationBasicCollection;

class ConfigurationGroupOptionDetailStruct extends ConfigurationGroupOptionBasicStruct
{
    /**
     * @var \Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationBasicCollection
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
