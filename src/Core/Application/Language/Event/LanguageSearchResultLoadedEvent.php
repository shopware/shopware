<?php declare(strict_types=1);

namespace Shopware\Application\Language\Event;

use Shopware\Application\Language\Struct\LanguageSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
