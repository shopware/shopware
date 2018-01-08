<?php declare(strict_types=1);

namespace Shopware\Api\Config\Collection;

use Shopware\Api\Config\Struct\ConfigFormFieldTranslationBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class ConfigFormFieldTranslationBasicCollection extends EntityCollection
{
    /**
     * @var ConfigFormFieldTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigFormFieldTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigFormFieldTranslationBasicStruct
    {
        return parent::current();
    }

    public function getConfigFormFieldIds(): array
    {
        return $this->fmap(function (ConfigFormFieldTranslationBasicStruct $configFormFieldTranslation) {
            return $configFormFieldTranslation->getConfigFormFieldId();
        });
    }

    public function filterByConfigFormFieldId(string $id): self
    {
        return $this->filter(function (ConfigFormFieldTranslationBasicStruct $configFormFieldTranslation) use ($id) {
            return $configFormFieldTranslation->getConfigFormFieldId() === $id;
        });
    }

    public function getLocaleIds(): array
    {
        return $this->fmap(function (ConfigFormFieldTranslationBasicStruct $configFormFieldTranslation) {
            return $configFormFieldTranslation->getLocaleId();
        });
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(function (ConfigFormFieldTranslationBasicStruct $configFormFieldTranslation) use ($id) {
            return $configFormFieldTranslation->getLocaleId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldTranslationBasicStruct::class;
    }
}
