<?php declare(strict_types=1);

namespace Shopware\System\Locale\Event;

use Shopware\System\Locale\Collection\LocaleBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class LocaleBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'locale.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var LocaleBasicCollection
     */
    protected $locales;

    public function __construct(LocaleBasicCollection $locales, ApplicationContext $context)
    {
        $this->context = $context;
        $this->locales = $locales;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getLocales(): LocaleBasicCollection
    {
        return $this->locales;
    }
}
