<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Collection;

use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionTranslationDetailStruct;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

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

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
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
