<?php declare(strict_types=1);

namespace Shopware\Shop\Struct;

use Shopware\Shop\Collection\ShopTemplateConfigFormFieldBasicCollection;

class ShopTemplateConfigFormDetailStruct extends ShopTemplateConfigFormBasicStruct
{
    /**
     * @var ShopTemplateConfigFormBasicStruct|null
     */
    protected $parent;

    /**
     * @var ShopTemplateBasicStruct
     */
    protected $shopTemplate;

    /**
     * @var ShopTemplateConfigFormFieldBasicCollection
     */
    protected $fields;

    public function __construct()
    {
        $this->fields = new ShopTemplateConfigFormFieldBasicCollection();
    }

    public function getParent(): ?ShopTemplateConfigFormBasicStruct
    {
        return $this->parent;
    }

    public function setParent(?ShopTemplateConfigFormBasicStruct $parent): void
    {
        $this->parent = $parent;
    }

    public function getShopTemplate(): ShopTemplateBasicStruct
    {
        return $this->shopTemplate;
    }

    public function setShopTemplate(ShopTemplateBasicStruct $shopTemplate): void
    {
        $this->shopTemplate = $shopTemplate;
    }

    public function getFields(): ShopTemplateConfigFormFieldBasicCollection
    {
        return $this->fields;
    }

    public function setFields(ShopTemplateConfigFormFieldBasicCollection $fields): void
    {
        $this->fields = $fields;
    }
}
