<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Event\Locale;

use Shopware\Api\Locale\Collection\LocaleBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class LocaleBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'locale.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var LocaleBasicCollection
     */
    protected $locales;

    public function __construct(LocaleBasicCollection $locales, TranslationContext $context)
    {
        $this->context = $context;
        $this->locales = $locales;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getLocales(): LocaleBasicCollection
    {
        return $this->locales;
    }
}
