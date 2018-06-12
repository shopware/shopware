<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;


class ConfigFormTranslationCollection extends EntityCollection
{
    /**
     * @var ConfigFormTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigFormTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigFormTranslationStruct
    {
        return parent::current();
    }

    public function getConfigFormIds(): array
    {
        return $this->fmap(function (ConfigFormTranslationStruct $configFormTranslation) {
            return $configFormTranslation->getConfigFormId();
        });
    }

    public function filterByConfigFormId(string $id): self
    {
        return $this->filter(function (ConfigFormTranslationStruct $configFormTranslation) use ($id) {
            return $configFormTranslation->getConfigFormId() === $id;
        });
    }

    public function getLocaleIds(): array
    {
        return $this->fmap(function (ConfigFormTranslationStruct $configFormTranslation) {
            return $configFormTranslation->getLocaleId();
        });
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(function (ConfigFormTranslationStruct $configFormTranslation) use ($id) {
            return $configFormTranslation->getLocaleId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormTranslationStruct::class;
    }
}
