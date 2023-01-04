<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('sales-channel')]
class SeoUrlUpdateEvent extends Event
{
    protected array $seoUrls;

    public function __construct(array $seoUrls)
    {
        $this->seoUrls = $seoUrls;
    }

    public function getSeoUrls(): array
    {
        return $this->seoUrls;
    }
}
