<?php declare(strict_types=1);

namespace Shopware\Core\System\Configuration\Aggregate\ConfigurationGroupTranslation\Collection;

use Shopware\Core\System\Language\Collection\LanguageBasicCollection;
use Shopware\Core\System\Configuration\Aggregate\ConfigurationGroupTranslation\Struct\ConfigurationGroupTranslationDetailStruct;
use Shopware\Core\System\Configuration\Collection\ConfigurationGroupBasicCollection;

class ConfigurationGroupTranslationDetailCollection extends ConfigurationGroupTranslationBasicCollection
{
    /**
     * @var \Shopware\Core\System\Configuration\Aggregate\ConfigurationGroupTranslation\Struct\ConfigurationGroupTranslationDetailStruct[]
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
