<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Navigation;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Storefront\Page\Page;

class NavigationPage extends Page
{
    /**
     * @var CmsPageEntity|null
     */
    protected $cmsPage;

    protected ?string $navigationId;

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }

    public function getNavigationId(): ?string
    {
        return $this->navigationId;
    }

    public function setNavigationId(?string $navigationId): void
    {
        $this->navigationId = $navigationId;
    }

    public function getEntityName(): string
    {
        return CategoryDefinition::ENTITY_NAME;
    }
}
