<?php declare(strict_types=1);

namespace Shopware\System\Locale\Aggregate\LocaleTranslation\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Locale\Aggregate\LocaleTranslation\Struct\LocaleTranslationSearchResult;

class LocaleTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'locale_translation.search.result.loaded';

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
