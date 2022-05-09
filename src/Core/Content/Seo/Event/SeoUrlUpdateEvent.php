<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Event;

use Symfony\Contracts\EventDispatcher\Event;

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
