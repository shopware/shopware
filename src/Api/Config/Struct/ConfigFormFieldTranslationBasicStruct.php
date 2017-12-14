<?php declare(strict_types=1);

namespace Shopware\Api\Config\Struct;

use Shopware\Api\Entity\Entity;

class ConfigFormFieldTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $configFormFieldUuid;

    /**
     * @var string
     */
    protected $localeUuid;

    /**
     * @var string|null
     */
    protected $label;

    /**
     * @var string|null
     */
    protected $description;

    public function getConfigFormFieldUuid(): string
    {
        return $this->configFormFieldUuid;
    }

    public function setConfigFormFieldUuid(string $configFormFieldUuid): void
    {
        $this->configFormFieldUuid = $configFormFieldUuid;
    }

    public function getLocaleUuid(): string
    {
        return $this->localeUuid;
    }

    public function setLocaleUuid(string $localeUuid): void
    {
        $this->localeUuid = $localeUuid;
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
}
