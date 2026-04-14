<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('inventory')]
class SeoUrlUpdateEvent extends Event
{
    /**
     * @param list<array<string, mixed>> $seoUrls
     */
    public function __construct(protected array $seoUrls)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSeoUrls(): array
    {
        return $this->seoUrls;
    }
}
