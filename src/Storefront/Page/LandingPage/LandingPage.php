<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\LandingPage;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\Feature;
use Shopware\Storefront\Page\Page;

/**
 * @package content
 */
class LandingPage extends Page
{
    /* @deprecated tag:v6.5.0 Trait will be removed. customFields will be found under the cmsPage and landingPage */
    use EntityCustomFieldsTrait;

    /**
     * @var CmsPageEntity|null
     *
     * @deprecated tag:v6.5.0 $cmsPage will be removed. Use LandingPage->getLandingPage()->getCmsPage() instead
     */
    protected $cmsPage;

    protected ?LandingPageEntity $landingPage;

    /* @deprecated tag:v6.5.0 $navigationId will be removed. Get the Id from LandingPage->getLandingPage()->getId() */
    protected ?string $navigationId;

    /**
     * @var array<mixed>|null
     */
    protected $customFields;

    /**
     * @deprecated tag:v6.5.0 getCmsPage will be removed. Use LandingPage->getLandingPage()->getCmsPage() instead
     */
    public function getCmsPage(): ?CmsPageEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, 'getCmsPage', 'v6.5.0.0', 'LandingPage->getLandingPage()->getCmsPage()')
        );

        return $this->cmsPage;
    }

    /**
     * @deprecated tag:v6.5.0 setCmsPage will be removed.
     */
    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, 'setCmsPage', 'v6.5.0.0')
        );

        $this->cmsPage = $cmsPage;
    }

    /**
     * @deprecated tag:v6.5.0 $navigationId will be removed. Get the Id from LandingPage->getLandingPage()->getId()
     */
    public function getNavigationId(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, 'getNavigationId', 'v6.5.0.0', 'LandingPage->getLandingPage()->getId()')
        );

        return $this->navigationId;
    }

    /**
     * @deprecated tag:v6.5.0 $navigationId will be removed.
     */
    public function setNavigationId(?string $navigationId): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, 'setNavigationId', 'v6.5.0.0')
        );

        $this->navigationId = $navigationId;
    }

    public function getEntityName(): string
    {
        return LandingPageDefinition::ENTITY_NAME;
    }

    public function getLandingPage(): ?LandingPageEntity
    {
        return $this->landingPage;
    }

    public function setLandingPage(?LandingPageEntity $landingPage): void
    {
        $this->landingPage = $landingPage;
    }
}
