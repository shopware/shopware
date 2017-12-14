<?php declare(strict_types=1);

namespace Shopware\Api\Config\Struct;

use Shopware\Api\Entity\Entity;

class ConfigFormTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $configFormUuid;

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

    public function getConfigFormUuid(): string
    {
        return $this->configFormUuid;
    }

    public function setConfigFormUuid(string $configFormUuid): void
    {
        $this->configFormUuid = $configFormUuid;
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
