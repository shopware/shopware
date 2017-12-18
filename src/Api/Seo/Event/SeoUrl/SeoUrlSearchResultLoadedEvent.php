<?php declare(strict_types=1);

namespace Shopware\Api\Seo\Event\SeoUrl;

use Shopware\Api\Seo\Struct\SeoUrlSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class SeoUrlSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'seo_url.search.result.loaded';

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
