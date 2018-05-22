<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Struct\ConfigFormFieldTranslationBasicStruct;

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
