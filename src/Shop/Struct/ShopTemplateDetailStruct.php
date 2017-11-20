<?php declare(strict_types=1);

namespace Shopware\Shop\Struct;

use Shopware\Plugin\Struct\PluginBasicStruct;
use Shopware\Shop\Collection\ShopBasicCollection;
use Shopware\Shop\Collection\ShopTemplateConfigFormBasicCollection;
use Shopware\Shop\Collection\ShopTemplateConfigFormFieldBasicCollection;
use Shopware\Shop\Collection\ShopTemplateConfigPresetBasicCollection;

class ShopTemplateDetailStruct extends ShopTemplateBasicStruct
{
    /**
     * @var PluginBasicStruct|null
     */
    protected $plugin;

    /**
     * @var ShopTemplateBasicStruct|null
     */
    protected $parent;

    /**
     * @var ShopBasicCollection
     */
    protected $shops;

    /**
     * @var ShopTemplateConfigFormBasicCollection
     */
    protected $configForms;

    /**
     * @var ShopTemplateConfigFormFieldBasicCollection
     */
    protected $configFormFields;

    /**
     * @var ShopTemplateConfigPresetBasicCollection
     */
    protected $configPresets;

    public function __construct()
    {
        $this->shops = new ShopBasicCollection();

        $this->configForms = new ShopTemplateConfigFormBasicCollection();

        $this->configFormFields = new ShopTemplateConfigFormFieldBasicCollection();

        $this->configPresets = new ShopTemplateConfigPresetBasicCollection();
    }

    public function getPlugin(): ?PluginBasicStruct
    {
        return $this->plugin;
    }

    public function setPlugin(?PluginBasicStruct $plugin): void
    {
        $this->plugin = $plugin;
    }

    public function getParent(): ?ShopTemplateBasicStruct
    {
        return $this->parent;
    }

    public function setParent(?ShopTemplateBasicStruct $parent): void
    {
        $this->parent = $parent;
    }

    public function getShops(): ShopBasicCollection
    {
        return $this->shops;
    }

    public function setShops(ShopBasicCollection $shops): void
    {
        $this->shops = $shops;
    }

    public function getConfigForms(): ShopTemplateConfigFormBasicCollection
    {
        return $this->configForms;
    }

    public function setConfigForms(ShopTemplateConfigFormBasicCollection $configForms): void
    {
        $this->configForms = $configForms;
    }

    public function getConfigFormFields(): ShopTemplateConfigFormFieldBasicCollection
    {
        return $this->configFormFields;
    }

    public function setConfigFormFields(ShopTemplateConfigFormFieldBasicCollection $configFormFields): void
    {
        $this->configFormFields = $configFormFields;
    }

    public function getConfigPresets(): ShopTemplateConfigPresetBasicCollection
    {
        return $this->configPresets;
    }

    public function setConfigPresets(ShopTemplateConfigPresetBasicCollection $configPresets): void
    {
        $this->configPresets = $configPresets;
    }
}
