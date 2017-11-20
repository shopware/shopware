<?php declare(strict_types=1);

namespace Shopware\Locale\Event\Locale;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Locale\Struct\LocaleSearchResult;

class LocaleSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'locale.search.result.loaded';

    /**
     * @var LocaleSearchResult
     */
    protected $result;

    public function __construct(LocaleSearchResult $result)
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
