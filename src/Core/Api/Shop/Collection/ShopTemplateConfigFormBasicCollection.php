<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\Api\Shop\Struct\ShopTemplateConfigFormBasicStruct;

class ShopTemplateConfigFormBasicCollection extends EntityCollection
{
    /**
     * @var ShopTemplateConfigFormBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ShopTemplateConfigFormBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ShopTemplateConfigFormBasicStruct
    {
        return parent::current();
    }

    public function getParentIds(): array
    {
        return $this->fmap(function (ShopTemplateConfigFormBasicStruct $shopTemplateConfigForm) {
            return $shopTemplateConfigForm->getParentId();
        });
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(function (ShopTemplateConfigFormBasicStruct $shopTemplateConfigForm) use ($id) {
            return $shopTemplateConfigForm->getParentId() === $id;
        });
    }

    public function getShopTemplateIds(): array
    {
        return $this->fmap(function (ShopTemplateConfigFormBasicStruct $shopTemplateConfigForm) {
            return $shopTemplateConfigForm->getShopTemplateId();
        });
    }

    public function filterByShopTemplateId(string $id): self
    {
        return $this->filter(function (ShopTemplateConfigFormBasicStruct $shopTemplateConfigForm) use ($id) {
            return $shopTemplateConfigForm->getShopTemplateId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateConfigFormBasicStruct::class;
    }
}
