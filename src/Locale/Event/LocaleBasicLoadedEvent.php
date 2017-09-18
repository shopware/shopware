<?php declare(strict_types=1);

namespace Shopware\Locale\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Locale\Struct\LocaleBasicCollection;

class LocaleBasicLoadedEvent extends NestedEvent
{
    const NAME = 'locale.basic.loaded';

    /**
     * @var LocaleBasicCollection
     */
    protected $locales;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(LocaleBasicCollection $locales, TranslationContext $context)
    {
        $this->locales = $locales;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getLocales(): LocaleBasicCollection
    {
        return $this->locales;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
        ]);
    }
}
