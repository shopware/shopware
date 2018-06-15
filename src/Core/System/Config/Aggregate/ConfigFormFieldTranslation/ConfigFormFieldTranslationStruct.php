<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Config\Aggregate\ConfigFormField\ConfigFormFieldStruct;
use Shopware\Core\System\Locale\LocaleStruct;

class ConfigFormFieldTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $configFormFieldId;

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
     * @var ConfigFormFieldStruct|null
     */
    protected $configFormField;

    /**
     * @var LocaleStruct|null
     */
    protected $locale;

    public function getConfigFormFieldId(): string
    {
        return $this->configFormFieldId;
    }

    public function setConfigFormFieldId(string $configFormFieldId): void
    {
        $this->configFormFieldId = $configFormFieldId;
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

    public function getConfigFormField(): ?ConfigFormFieldStruct
    {
        return $this->configFormField;
    }

    public function setConfigFormField(ConfigFormFieldStruct $configFormField): void
    {
        $this->configFormField = $configFormField;
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
