<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Collection;

use Shopware\System\Configuration\Struct\ConfigurationGroupTranslationDetailStruct;
use Shopware\Application\Language\Collection\LanguageBasicCollection;

class ConfigurationGroupTranslationDetailCollection extends ConfigurationGroupTranslationBasicCollection
{
    /**
     * @var ConfigurationGroupTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getConfigurationGroups(): ConfigurationGroupBasicCollection
    {
        return new ConfigurationGroupBasicCollection(
            $this->fmap(function (ConfigurationGroupTranslationDetailStruct $configurationGroupTranslation) {
                return $configurationGroupTranslation->getConfigurationGroup();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (ConfigurationGroupTranslationDetailStruct $configurationGroupTranslation) {
                return $configurationGroupTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ConfigurationGroupTranslationDetailStruct::class;
    }
}
