<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Shop\Struct\ShopTemplateConfigFormBasicStruct;

class ShopTemplateConfigFormBasicCollection extends EntityCollection
{
    /**
     * @var ShopTemplateConfigFormBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ShopTemplateConfigFormBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ShopTemplateConfigFormBasicStruct
    {
        return parent::current();
    }

    public function getParentUuids(): array
    {
        return $this->fmap(function (ShopTemplateConfigFormBasicStruct $shopTemplateConfigForm) {
            return $shopTemplateConfigForm->getParentUuid();
        });
    }

    public function filterByParentUuid(string $uuid): ShopTemplateConfigFormBasicCollection
    {
        return $this->filter(function (ShopTemplateConfigFormBasicStruct $shopTemplateConfigForm) use ($uuid) {
            return $shopTemplateConfigForm->getParentUuid() === $uuid;
        });
    }

    public function getShopTemplateUuids(): array
    {
        return $this->fmap(function (ShopTemplateConfigFormBasicStruct $shopTemplateConfigForm) {
            return $shopTemplateConfigForm->getShopTemplateUuid();
        });
    }

    public function filterByShopTemplateUuid(string $uuid): ShopTemplateConfigFormBasicCollection
    {
        return $this->filter(function (ShopTemplateConfigFormBasicStruct $shopTemplateConfigForm) use ($uuid) {
            return $shopTemplateConfigForm->getShopTemplateUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateConfigFormBasicStruct::class;
    }
}
