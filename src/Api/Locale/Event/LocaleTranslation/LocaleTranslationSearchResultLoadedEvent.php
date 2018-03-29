<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Event\LocaleTranslation;

use Shopware\Api\Locale\Struct\LocaleTranslationSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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
