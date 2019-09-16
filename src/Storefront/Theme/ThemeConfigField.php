<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Struct\Struct;

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

    /**
     * @var mixed
     */
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
     * @var array|null
     */
    protected $custom;

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

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function setOrder(?int $order): void
    {
        $this->order = $order;
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
}
