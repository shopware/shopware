<?php declare(strict_types=1);

namespace Shopware\Locale\Event\LocaleTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Locale\Collection\LocaleTranslationBasicCollection;

class LocaleTranslationBasicLoadedEvent extends NestedEvent
{
    const NAME = 'locale_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var LocaleTranslationBasicCollection
     */
    protected $localeTranslations;

    public function __construct(LocaleTranslationBasicCollection $localeTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->localeTranslations = $localeTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getLocaleTranslations(): LocaleTranslationBasicCollection
    {
        return $this->localeTranslations;
    }
}
