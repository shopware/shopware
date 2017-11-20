<?php declare(strict_types=1);

namespace Shopware\Seo\Event\SeoUrl;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Seo\Struct\SeoUrlSearchResult;

class SeoUrlSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'seo_url.search.result.loaded';

    /**
     * @var SeoUrlSearchResult
     */
    protected $result;

    public function __construct(SeoUrlSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
