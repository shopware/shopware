<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormField;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation\ConfigFormFieldTranslationCollection;
use Shopware\Core\System\Config\Aggregate\ConfigFormFieldValue\ConfigFormFieldValueCollection;
use Shopware\Core\System\Config\ConfigFormStruct;

class ConfigFormFieldStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $configFormId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string|null
     */
    protected $value;

    /**
     * @var bool
     */
    protected $required;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var int
     */
    protected $scope;

    /**
     * @var string|null
     */
    protected $options;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var string|null
     */
    protected $label;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var ConfigFormStruct|null
     */
    protected $configForm;

    /**
     * @var ConfigFormFieldTranslationCollection|null
     */
    protected $translations;

    /**
     * @var ConfigFormFieldValueCollection|null
     */
    protected $values;

    public function getConfigFormId(): ?string
    {
        return $this->configFormId;
    }

    public function setConfigFormId(?string $configFormId): void
    {
        $this->configFormId = $configFormId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): void
    {
        $this->required = $required;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getScope(): int
    {
        return $this->scope;
    }

    public function setScope(int $scope): void
    {
        $this->scope = $scope;
    }

    public function getOptions(): ?string
    {
        return $this->options;
    }

    public function setOptions(?string $options): void
    {
        $this->options = $options;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getConfigForm(): ?ConfigFormStruct
    {
        return $this->configForm;
    }

    public function setConfigForm(ConfigFormStruct $configForm): void
    {
        $this->configForm = $configForm;
    }

    public function getTranslations(): ?ConfigFormFieldTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ConfigFormFieldTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getValues(): ?ConfigFormFieldValueCollection
    {
        return $this->values;
    }

    public function setValues(ConfigFormFieldValueCollection $values): void
    {
        $this->values = $values;
    }
}
