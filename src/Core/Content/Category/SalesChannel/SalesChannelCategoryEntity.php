<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelCategoryEntity extends CategoryEntity
{
    protected ?string $seoUrl = null;

    protected ?BreadcrumbCollection $seoBreadcrumb = null;

    public function getSeoUrl(): ?string
    {
        return $this->seoUrl;
    }

    public function setSeoUrl(string $seoUrl): void
    {
        $this->seoUrl = $seoUrl;
    }

    public function getSeoBreadcrumb(): ?BreadcrumbCollection
    {
        return $this->seoBreadcrumb;
    }

    public function setSeoBreadcrumb(?BreadcrumbCollection $seoBreadcrumb): void
    {
        $this->seoBreadcrumb = $seoBreadcrumb;
    }
}
