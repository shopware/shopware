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

    public function get(string $uuid): ? ConfigFormFieldTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ConfigFormFieldTranslationBasicStruct
    {
        return parent::current();
    }

    public function getConfigFormFieldUuids(): array
    {
        return $this->fmap(function (ConfigFormFieldTranslationBasicStruct $configFormFieldTranslation) {
            return $configFormFieldTranslation->getConfigFormFieldUuid();
        });
    }

    public function filterByConfigFormFieldUuid(string $uuid): ConfigFormFieldTranslationBasicCollection
    {
        return $this->filter(function (ConfigFormFieldTranslationBasicStruct $configFormFieldTranslation) use ($uuid) {
            return $configFormFieldTranslation->getConfigFormFieldUuid() === $uuid;
        });
    }

    public function getLocaleUuids(): array
    {
        return $this->fmap(function (ConfigFormFieldTranslationBasicStruct $configFormFieldTranslation) {
            return $configFormFieldTranslation->getLocaleUuid();
        });
    }

    public function filterByLocaleUuid(string $uuid): ConfigFormFieldTranslationBasicCollection
    {
        return $this->filter(function (ConfigFormFieldTranslationBasicStruct $configFormFieldTranslation) use ($uuid) {
            return $configFormFieldTranslation->getLocaleUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormFieldTranslationBasicStruct::class;
    }
}
