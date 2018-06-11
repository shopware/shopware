<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\Struct;

use Shopware\Core\Framework\ORM\Entity;

class ConfigFormTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $configFormId;

    /**
     * @var string
     */
    protected $localeId;

    /**
     * @var string|null
     */
    protected $label;

    /**
     * @var string|null
     */
    protected $description;

    public function getConfigFormId(): string
    {
        return $this->configFormId;
    }

    public function setConfigFormId(string $configFormId): void
    {
        $this->configFormId = $configFormId;
    }

    public function getLocaleId(): string
    {
        return $this->localeId;
    }

    public function setLocaleId(string $localeId): void
    {
        $this->localeId = $localeId;
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
