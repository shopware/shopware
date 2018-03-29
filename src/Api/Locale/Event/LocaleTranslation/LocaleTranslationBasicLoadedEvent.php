<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Event\LocaleTranslation;

use Shopware\Api\Locale\Collection\LocaleTranslationBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class LocaleTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'locale_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var LocaleTranslationBasicCollection
     */
    protected $localeTranslations;

    public function __construct(LocaleTranslationBasicCollection $localeTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->localeTranslations = $localeTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getLocaleTranslations(): LocaleTranslationBasicCollection
    {
        return $this->localeTranslations;
    }
}
