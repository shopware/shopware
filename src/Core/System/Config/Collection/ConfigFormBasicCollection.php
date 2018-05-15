<?php declare(strict_types=1);

namespace Shopware\System\Config\Collection;

use Shopware\System\Config\Struct\ConfigFormBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

class ConfigFormBasicCollection extends EntityCollection
{
    /**
     * @var ConfigFormBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ConfigFormBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ConfigFormBasicStruct
    {
        return parent::current();
    }

    public function getParentIds(): array
    {
        return $this->fmap(function (ConfigFormBasicStruct $configForm) {
            return $configForm->getParentId();
        });
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(function (ConfigFormBasicStruct $configForm) use ($id) {
            return $configForm->getParentId() === $id;
        });
    }

    public function getPluginIds(): array
    {
        return $this->fmap(function (ConfigFormBasicStruct $configForm) {
            return $configForm->getPluginId();
        });
    }

    public function filterByPluginId(string $id): self
    {
        return $this->filter(function (ConfigFormBasicStruct $configForm) use ($id) {
            return $configForm->getPluginId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ConfigFormBasicStruct::class;
    }
}
