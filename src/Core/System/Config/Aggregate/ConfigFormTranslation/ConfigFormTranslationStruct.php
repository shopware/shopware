<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormTranslation;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Config\ConfigFormStruct;
use Shopware\Core\System\Locale\LocaleStruct;

class ConfigFormTranslationStruct extends Entity
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

    /**
     * @var ConfigFormStruct|null
     */
    protected $configForm;

    /**
     * @var LocaleStruct|null
     */
    protected $locale;

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

    public function getConfigForm(): ?ConfigFormStruct
    {
        return $this->configForm;
    }

    public function setConfigForm(ConfigFormStruct $configForm): void
    {
        $this->configForm = $configForm;
    }

    public function getLocale(): ?LocaleStruct
    {
        return $this->locale;
    }

    public function setLocale(LocaleStruct $locale): void
    {
        $this->locale = $locale;
    }
}
