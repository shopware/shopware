<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo\Event\SeoUrl;

use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Api\Seo\Collection\SeoUrlDetailCollection;

class SeoUrlDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'seo_url.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var SeoUrlDetailCollection
     */
    protected $seoUrls;

    public function __construct(SeoUrlDetailCollection $seoUrls, ApplicationContext $context)
    {
        $this->context = $context;
        $this->seoUrls = $seoUrls;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getSeoUrls(): SeoUrlDetailCollection
    {
        return $this->seoUrls;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->seoUrls->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->seoUrls->getShops(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
