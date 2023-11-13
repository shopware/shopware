<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('storefront')]
class ThemeConfigField extends Struct
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $label;

    /**
     * @var array|null
     */
    protected $helpText;

    /**
     * @var string
     */
    protected $type;

    protected $value;

    /**
     * @var bool|null
     */
    protected $editable;

    /**
     * @var string|null
     */
    protected $block;

    /**
     * @var string|null
     */
    protected $section;

    /**
     * @var string|null
     */
    protected $tab;

    /**
     * @var int|null
     */
    protected $order;

    /**
     * @var int|null
     */
    protected $sectionOrder;

    /**
     * @var int|null
     */
    protected $blockOrder;

    /**
     * @var int|null
     */
    protected $tabOrder;

    /**
     * @var array|null
     */
    protected $custom;

    /**
     * @var bool|null
     */
    protected $scss;

    /**
     * @var bool|null
     */
    protected $fullWidth;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLabel(): ?array
    {
        return $this->label;
    }

    public function setLabel(?array $label): void
    {
        $this->label = $label;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getEditable(): ?bool
    {
        return $this->editable;
    }

    public function setEditable(?bool $editable): void
    {
        $this->editable = $editable;
    }

    public function getBlock(): ?string
    {
        return $this->block;
    }

    public function setBlock(?string $block): void
    {
        $this->block = $block;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function setSection(?string $section): void
    {
        $this->section = $section;
    }

    public function getTab(): ?string
    {
        return $this->tab;
    }

    public function setTab(?string $tab): void
    {
        $this->tab = $tab;
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function setOrder(?int $order): void
    {
        $this->order = $order;
    }

    public function getTabOrder(): ?int
    {
        return $this->tabOrder;
    }

    public function setTabOrder(?int $tabOrder): void
    {
        $this->tabOrder = $tabOrder;
    }

    public function getSectionOrder(): ?int
    {
        return $this->sectionOrder;
    }

    public function setSectionOrder(?int $sectionOrder): void
    {
        $this->sectionOrder = $sectionOrder;
    }

    public function getBlockOrder(): ?int
    {
        return $this->blockOrder;
    }

    public function setBlockOrder(?int $blockOrder): void
    {
        $this->blockOrder = $blockOrder;
    }

    public function getHelpText(): ?array
    {
        return $this->helpText;
    }

    public function setHelpText(?array $helpText): void
    {
        $this->helpText = $helpText;
    }

    public function getCustom(): ?array
    {
        return $this->custom;
    }

    public function setCustom(?array $custom): void
    {
        $this->custom = $custom;
    }

    public function getScss(): ?bool
    {
        return $this->scss;
    }

    public function setScss(?bool $scss): void
    {
        $this->scss = $scss;
    }

    public function getFullWidth(): ?bool
    {
        return $this->fullWidth;
    }

    public function setFullWidth(?bool $fullWidth): void
    {
        $this->fullWidth = $fullWidth;
    }
}
