<?php declare(strict_types=1);

namespace Shopware\Core\System\Config;

use Shopware\Core\Framework\ORM\EntityCollection;

class ConfigFormCollection extends EntityCollection
{
    /**
     * @var ConfigFormStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigFormStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigFormStruct
    {
        return parent::current();
    }

    public function getParentIds(): array
    {
        return $this->fmap(function (ConfigFormStruct $configForm) {
            return $configForm->getParentId();
        });
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(function (ConfigFormStruct $configForm) use ($id) {
            return $configForm->getParentId() === $id;
        });
    }

    public function getPluginIds(): array
    {
        return $this->fmap(function (ConfigFormStruct $configForm) {
            return $configForm->getPluginId();
        });
    }

    public function filterByPluginId(string $id): self
    {
        return $this->filter(function (ConfigFormStruct $configForm) use ($id) {
            return $configForm->getPluginId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormStruct::class;
    }
}
