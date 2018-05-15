<?php declare(strict_types=1);

namespace Shopware\System\Config\Collection;

use Shopware\System\Config\Struct\ConfigFormFieldTranslationDetailStruct;
use Shopware\Api\Locale\Collection\LocaleBasicCollection;

class ConfigFormFieldTranslationDetailCollection extends ConfigFormFieldTranslationBasicCollection
{
    /**
     * @var ConfigFormFieldTranslationDetailStruct[]
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
