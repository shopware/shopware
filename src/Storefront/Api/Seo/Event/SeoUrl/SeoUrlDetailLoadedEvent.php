<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo\Event\SeoUrl;

use Shopware\Application\Application\Event\ApplicationBasicLoadedEvent;
use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Api\Seo\Collection\SeoUrlDetailCollection;

class SeoUrlDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'seo_url.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var SeoUrlDetailCollection
     */
    protected $seoUrls;

    public function __construct(SeoUrlDetailCollection $seoUrls, Context $context)
    {
        $this->context = $context;
        $this->seoUrls = $seoUrls;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
        if ($this->seoUrls->getApplications()->count() > 0) {
            $events[] = new ApplicationBasicLoadedEvent($this->seoUrls->getApplications(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
