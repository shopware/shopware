<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection;

use Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\Collection\ConfigurationGroupOptionBasicCollection;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Collection\ConfigurationGroupOptionTranslationBasicCollection;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOptionTranslation\Struct\ConfigurationGroupOptionTranslationDetailStruct;
use Shopware\Application\Language\Collection\LanguageBasicCollection;

class ConfigurationGroupOptionTranslationDetailCollection extends ConfigurationGroupOptionTranslationBasicCollection
{
    /**
     * @var ConfigurationGroupOptionTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getConfigurationGroupOptions(): ConfigurationGroupOptionBasicCollection
    {
        return new ConfigurationGroupOptionBasicCollection(
            $this->fmap(function (ConfigurationGroupOptionTranslationDetailStruct $configurationGroupOptionTranslation) {
                return $configurationGroupOptionTranslation->getConfigurationGroupOption();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (ConfigurationGroupOptionTranslationDetailStruct $configurationGroupOptionTranslation) {
                return $configurationGroupOptionTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupOptionTranslationDetailStruct::class;
    }
}
