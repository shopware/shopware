<?php declare(strict_types=1);

namespace Shopware\Seo\Event\SeoUrl;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Seo\Collection\SeoUrlBasicCollection;

class SeoUrlBasicLoadedEvent extends NestedEvent
{
    const NAME = 'seo_url.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var SeoUrlBasicCollection
     */
    protected $seoUrls;

    public function __construct(SeoUrlBasicCollection $seoUrls, TranslationContext $context)
    {
        $this->context = $context;
        $this->seoUrls = $seoUrls;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getSeoUrls(): SeoUrlBasicCollection
    {
        return $this->seoUrls;
    }
}
