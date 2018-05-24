<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Collection;

use Shopware\System\Config\Aggregate\ConfigFormField\Collection\ConfigFormFieldBasicCollection;
use Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Struct\ConfigFormFieldTranslationDetailStruct;
use Shopware\System\Locale\Collection\LocaleBasicCollection;

class ConfigFormFieldTranslationDetailCollection extends ConfigFormFieldTranslationBasicCollection
{
    /**
     * @var \Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Struct\ConfigFormFieldTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getConfigFormFields(): ConfigFormFieldBasicCollection
    {
        return new ConfigFormFieldBasicCollection(
            $this->fmap(function (ConfigFormFieldTranslationDetailStruct $configFormFieldTranslation) {
                return $configFormFieldTranslation->getConfigFormField();
            })
        );
    }

    public function getLocales(): LocaleBasicCollection
    {
        return new LocaleBasicCollection(
            $this->fmap(function (ConfigFormFieldTranslationDetailStruct $configFormFieldTranslation) {
                return $configFormFieldTranslation->getLocale();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldTranslationDetailStruct::class;
    }
}
