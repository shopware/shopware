<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo\Event\SeoUrl;

use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Storefront\Api\Seo\Collection\SeoUrlBasicCollection;

class SeoUrlBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'seo_url.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var SeoUrlBasicCollection
     */
    protected $seoUrls;

    public function __construct(SeoUrlBasicCollection $seoUrls, ApplicationContext $context)
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

    public function getSeoUrls(): SeoUrlBasicCollection
    {
        return $this->seoUrls;
    }
}
