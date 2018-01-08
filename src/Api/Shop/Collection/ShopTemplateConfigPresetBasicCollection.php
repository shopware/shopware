<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Shop\Struct\ShopTemplateConfigPresetBasicStruct;

class ShopTemplateConfigPresetBasicCollection extends EntityCollection
{
    /**
     * @var ShopTemplateConfigPresetBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ShopTemplateConfigPresetBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ShopTemplateConfigPresetBasicStruct
    {
        return parent::current();
    }

    public function getShopTemplateIds(): array
    {
        return $this->fmap(function (ShopTemplateConfigPresetBasicStruct $shopTemplateConfigPreset) {
            return $shopTemplateConfigPreset->getShopTemplateId();
        });
    }

    public function filterByShopTemplateId(string $id): self
    {
        return $this->filter(function (ShopTemplateConfigPresetBasicStruct $shopTemplateConfigPreset) use ($id) {
            return $shopTemplateConfigPreset->getShopTemplateId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateConfigPresetBasicStruct::class;
    }
}
