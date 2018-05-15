<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

use Shopware\Framework\Plugin\Struct\PluginBasicStruct;
use Shopware\Api\Shop\Collection\ShopTemplateConfigFormBasicCollection;
use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldBasicCollection;
use Shopware\Api\Shop\Collection\ShopTemplateConfigPresetBasicCollection;

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
