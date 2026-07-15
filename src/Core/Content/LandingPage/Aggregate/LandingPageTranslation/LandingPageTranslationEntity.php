<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\Aggregate\LandingPageTranslation;

use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class LandingPageTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $landingPageId;

    protected ?LandingPageEntity $landingPage = null;

    protected ?string $name = null;

    protected ?string $url = null;

    protected ?string $metaTitle = null;

    protected ?string $metaDescription = null;

    protected ?string $keywords = null;

    /**
     * @var array<string, array<string, array<string, mixed>>|null>|null
     */
    protected ?array $slotConfig = null;

    public function getLandingPageId(): string
    {
        return $this->landingPageId;
    }

    public function setLandingPageId(string $landingPageId): void
    {
        $this->landingPageId = $landingPageId;
    }

    public function getLandingPage(): ?LandingPageEntity
    {
        return $this->landingPage;
    }

    public function setLandingPage(?LandingPageEntity $landingPage): void
    {
        $this->landingPage = $landingPage;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
    }

    /**
     * @return array<string, array<string, array<string, mixed>>|null>|null
     */
    public function getSlotConfig(): ?array
    {
        return $this->slotConfig;
    }

    /**
     * @deprecated tag:v6.8.0 - $slotConfig will be mandatory in future implementation
     *
     * @param array<string, array<string, array<string, mixed>>|null>|null $slotConfig
     */
    public function setSlotConfig(?array $slotConfig): void
    {
        if ($slotConfig === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                '$slotConfig will be mandatory in future implementation'
            );
        }

        $this->slotConfig = $slotConfig;
    }
}
