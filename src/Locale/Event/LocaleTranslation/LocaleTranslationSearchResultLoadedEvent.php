<?php declare(strict_types=1);

namespace Shopware\Locale\Event\LocaleTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Locale\Struct\LocaleTranslationSearchResult;

class LocaleTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'locale_translation.search.result.loaded';

    /**
     * @var LocaleTranslationSearchResult
     */
    protected $result;

    public function __construct(LocaleTranslationSearchResult $result)
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
