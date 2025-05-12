<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Aggregate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ThemeEntity;

#[Package('framework')]
class ThemeTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected ?string $themeId = null;

    protected ?string $description = null;

    /**
     * @var array<string, string>|null
     *
     * @deprecated tag:v6.8.0 - Will be removed. Use label snippet keys from structured fields instead
     */
    protected ?array $labels = null;

    /**
     * @var array<string, string>|null
     *
     * @deprecated tag:v6.8.0 - Will be removed. Use helpText snippet keys from structured fields instead
     */
    protected ?array $helpTexts = null;

    protected ?ThemeEntity $theme = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use label snippet keys from structured fields instead
     *
     * @return array<string, string>|null
     */
    public function getLabels(): ?array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0', 'ThemeConfigField::getLabelSnippetKey'));

        return $this->labels;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use label snippet keys from structured fields instead
     *
     * @param array<string, string>|null $labels
     */
    public function setLabels(?array $labels): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'));

        $this->labels = $labels;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use helpText snippet keys from structured fields instead
     *
     * @return array<string, string>|null
     */
    public function getHelpTexts(): ?array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0', 'ThemeConfigField::getHelpTextSnippetKey'));

        return $this->helpTexts;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use helpText snippet keys from structured fields instead
     *
     * @param array<string, string>|null $helpTexts
     */
    public function setHelpTexts(?array $helpTexts): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0'));

        $this->helpTexts = $helpTexts;
    }

    public function getThemeId(): ?string
    {
        return $this->themeId;
    }

    public function setThemeId(?string $themeId): void
    {
        $this->themeId = $themeId;
    }

    public function getTheme(): ?ThemeEntity
    {
        return $this->theme;
    }

    public function setTheme(?ThemeEntity $theme): void
    {
        $this->theme = $theme;
    }
}
