<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Event\LocaleTranslation;

use Shopware\Api\Locale\Collection\LocaleTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class LocaleTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'locale_translation.basic.loaded';

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
