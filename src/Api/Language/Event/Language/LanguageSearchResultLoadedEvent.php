<?php declare(strict_types=1);

namespace Shopware\Api\Language\Event\Language;

use Shopware\Api\Language\Struct\LanguageSearchResult;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class LanguageSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'language.search.result.loaded';

    /**
     * @var LanguageSearchResult
     */
    protected $result;

    public function __construct(LanguageSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->result->getContext();
    }
}
