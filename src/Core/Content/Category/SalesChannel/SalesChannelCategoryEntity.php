<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class SalesChannelCategoryEntity extends CategoryEntity
{
    protected ?string $seoLink = null;

    public function getSeoLink(): ?string
    {
        return $this->seoLink;
    }

    public function setSeoLink(string $seoLink): void
    {
        $this->seoLink = $seoLink;
    }
}
