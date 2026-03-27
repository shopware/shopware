<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('inventory')]
class SeoUrlUpdateEvent extends Event implements ShopwareEvent
{
    /**
     * @param list<array<string, mixed>> $seoUrls
     */
    public function __construct(
        protected array $seoUrls,
        private readonly Context $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSeoUrls(): array
    {
        return $this->seoUrls;
    }
}
