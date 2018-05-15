<?php declare(strict_types=1);

namespace Shopware\System\Config\Collection;

use Shopware\System\Config\Struct\ConfigFormTranslationDetailStruct;
use Shopware\System\Locale\Collection\LocaleBasicCollection;

class ConfigFormTranslationDetailCollection extends ConfigFormTranslationBasicCollection
{
    /**
     * @var ConfigFormTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getConfigForms(): ConfigFormBasicCollection
    {
        return new ConfigFormBasicCollection(
            $this->fmap(function (ConfigFormTranslationDetailStruct $configFormTranslation) {
                return $configFormTranslation->getConfigForm();
            })
        );
    }

    public function getLocales(): LocaleBasicCollection
    {
        return new LocaleBasicCollection(
            $this->fmap(function (ConfigFormTranslationDetailStruct $configFormTranslation) {
                return $configFormTranslation->getLocale();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormTranslationDetailStruct::class;
    }
}
