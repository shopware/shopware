<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo\Event\SeoUrl;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Storefront\Api\Seo\Collection\SeoUrlBasicCollection;

class SeoUrlBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'seo_url.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var SeoUrlBasicCollection
     */
    protected $seoUrls;

    public function __construct(SeoUrlBasicCollection $seoUrls, Context $context)
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

    public function getSeoUrls(): SeoUrlBasicCollection
    {
        return $this->seoUrls;
    }
}
