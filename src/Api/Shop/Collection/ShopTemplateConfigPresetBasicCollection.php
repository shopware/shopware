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

    public function get(string $uuid): ? ShopTemplateConfigPresetBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ShopTemplateConfigPresetBasicStruct
    {
        return parent::current();
    }

    public function getShopTemplateUuids(): array
    {
        return $this->fmap(function (ShopTemplateConfigPresetBasicStruct $shopTemplateConfigPreset) {
            return $shopTemplateConfigPreset->getShopTemplateUuid();
        });
    }

    public function filterByShopTemplateUuid(string $uuid): ShopTemplateConfigPresetBasicCollection
    {
        return $this->filter(function (ShopTemplateConfigPresetBasicStruct $shopTemplateConfigPreset) use ($uuid) {
            return $shopTemplateConfigPreset->getShopTemplateUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ShopTemplateConfigPresetBasicStruct::class;
    }
}
