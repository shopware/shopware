<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Aggregate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ThemeEntity;

#[Package('storefront')]
class ThemeTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $themeId;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $description;

    /**
     * @var array|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $labels;

    /**
     * @var array|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $helpTexts;

    /**
     * @var ThemeEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $theme;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getLabels(): ?array
    {
        return $this->labels;
    }

    public function setLabels(?array $labels): void
    {
        $this->labels = $labels;
    }

    public function getHelpTexts(): ?array
    {
        return $this->helpTexts;
    }

    public function setHelpTexts(?array $helpTexts): void
    {
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
