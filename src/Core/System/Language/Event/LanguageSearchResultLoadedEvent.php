<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\Struct\LanguageSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

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
