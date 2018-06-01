<?php declare(strict_types=1);

namespace Shopware\System\Locale\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Locale\Collection\LocaleBasicCollection;

class LocaleBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'locale.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var LocaleBasicCollection
     */
    protected $locales;

    public function __construct(LocaleBasicCollection $locales, Context $context)
    {
        $this->context = $context;
        $this->locales = $locales;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLocales(): LocaleBasicCollection
    {
        return $this->locales;
    }
}
