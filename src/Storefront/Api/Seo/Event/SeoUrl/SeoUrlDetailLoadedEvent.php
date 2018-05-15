<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo\Event\SeoUrl;

use Shopware\Application\Application\Event\Application\ApplicationBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
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
        if ($this->seoUrls->getApplications()->count() > 0) {
            $events[] = new ApplicationBasicLoadedEvent($this->seoUrls->getApplications(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
