<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Aggregate\LocaleTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\Collection\LocaleTranslationBasicCollection;

class LocaleTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'locale_translation.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var LocaleTranslationBasicCollection
     */
    protected $localeTranslations;

    public function __construct(LocaleTranslationBasicCollection $localeTranslations, Context $context)
    {
        $this->context = $context;
        $this->localeTranslations = $localeTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLocaleTranslations(): LocaleTranslationBasicCollection
    {
        return $this->localeTranslations;
    }
}
