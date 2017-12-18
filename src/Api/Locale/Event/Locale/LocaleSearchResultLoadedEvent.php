<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Event\Locale;

use Shopware\Api\Locale\Struct\LocaleSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class LocaleSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'locale.search.result.loaded';

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
