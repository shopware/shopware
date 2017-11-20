<?php declare(strict_types=1);

namespace Shopware\Config\Struct;

use Shopware\Config\Collection\ConfigFormFieldBasicCollection;
use Shopware\Config\Collection\ConfigFormTranslationBasicCollection;
use Shopware\Plugin\Struct\PluginBasicStruct;

class ConfigFormDetailStruct extends ConfigFormBasicStruct
{
    /**
     * @var ConfigFormBasicStruct|null
     */
    protected $parent;

    /**
     * @var PluginBasicStruct|null
     */
    protected $plugin;

    /**
     * @var ConfigFormFieldBasicCollection
     */
    protected $fields;

    /**
     * @var ConfigFormTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->fields = new ConfigFormFieldBasicCollection();

        $this->translations = new ConfigFormTranslationBasicCollection();
    }

    public function getParent(): ?ConfigFormBasicStruct
    {
        return $this->parent;
    }

    public function setParent(?ConfigFormBasicStruct $parent): void
    {
        $this->parent = $parent;
    }

    public function getPlugin(): ?PluginBasicStruct
    {
        return $this->plugin;
    }

    public function setPlugin(?PluginBasicStruct $plugin): void
    {
        $this->plugin = $plugin;
    }

    public function getFields(): ConfigFormFieldBasicCollection
    {
        return $this->fields;
    }

    public function setFields(ConfigFormFieldBasicCollection $fields): void
    {
        $this->fields = $fields;
    }

    public function getTranslations(): ConfigFormTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(ConfigFormTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
