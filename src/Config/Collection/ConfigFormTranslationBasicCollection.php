<?php declare(strict_types=1);

namespace Shopware\Config\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Config\Struct\ConfigFormTranslationBasicStruct;

class ConfigFormTranslationBasicCollection extends EntityCollection
{
    /**
     * @var ConfigFormTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ConfigFormTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ConfigFormTranslationBasicStruct
    {
        return parent::current();
    }

    public function getConfigFormUuids(): array
    {
        return $this->fmap(function (ConfigFormTranslationBasicStruct $configFormTranslation) {
            return $configFormTranslation->getConfigFormUuid();
        });
    }

    public function filterByConfigFormUuid(string $uuid): ConfigFormTranslationBasicCollection
    {
        return $this->filter(function (ConfigFormTranslationBasicStruct $configFormTranslation) use ($uuid) {
            return $configFormTranslation->getConfigFormUuid() === $uuid;
        });
    }

    public function getLocaleUuids(): array
    {
        return $this->fmap(function (ConfigFormTranslationBasicStruct $configFormTranslation) {
            return $configFormTranslation->getLocaleUuid();
        });
    }

    public function filterByLocaleUuid(string $uuid): ConfigFormTranslationBasicCollection
    {
        return $this->filter(function (ConfigFormTranslationBasicStruct $configFormTranslation) use ($uuid) {
            return $configFormTranslation->getLocaleUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormTranslationBasicStruct::class;
    }
}
