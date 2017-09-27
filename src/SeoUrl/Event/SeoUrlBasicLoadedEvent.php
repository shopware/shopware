<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\SeoUrl\Struct\SeoUrlBasicCollection;

class SeoUrlBasicLoadedEvent extends NestedEvent
{
    const NAME = 'seoUrl.basic.loaded';

    /**
     * @var SeoUrlBasicCollection
     */
    protected $seoUrls;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(SeoUrlBasicCollection $seoUrls, TranslationContext $context)
    {
        $this->seoUrls = $seoUrls;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSeoUrls(): SeoUrlBasicCollection
    {
        return $this->seoUrls;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];

        return new NestedEventCollection($events);
    }
}
