<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;


class ConfigFormFieldTranslationCollection extends EntityCollection
{
    /**
     * @var ConfigFormFieldTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigFormFieldTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigFormFieldTranslationStruct
    {
        return parent::current();
    }

    public function getConfigFormFieldIds(): array
    {
        return $this->fmap(function (ConfigFormFieldTranslationStruct $configFormFieldTranslation) {
            return $configFormFieldTranslation->getConfigFormFieldId();
        });
    }

    public function filterByConfigFormFieldId(string $id): self
    {
        return $this->filter(function (ConfigFormFieldTranslationStruct $configFormFieldTranslation) use ($id) {
            return $configFormFieldTranslation->getConfigFormFieldId() === $id;
        });
    }

    public function getLocaleIds(): array
    {
        return $this->fmap(function (ConfigFormFieldTranslationStruct $configFormFieldTranslation) {
            return $configFormFieldTranslation->getLocaleId();
        });
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(function (ConfigFormFieldTranslationStruct $configFormFieldTranslation) use ($id) {
            return $configFormFieldTranslation->getLocaleId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldTranslationStruct::class;
    }
}
