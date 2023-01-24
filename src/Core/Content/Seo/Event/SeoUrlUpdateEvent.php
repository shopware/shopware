<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package sales-channel
 */
class SeoUrlUpdateEvent extends Event
{
    public function __construct(protected array $seoUrls)
    {
    }

    public function getSeoUrls(): array
    {
        return $this->seoUrls;
    }
}
