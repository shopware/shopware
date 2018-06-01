<?php declare(strict_types=1);

namespace Shopware\System\Language\Event;

use Shopware\Framework\Context;
use Shopware\System\Language\Struct\LanguageSearchResult;
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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
