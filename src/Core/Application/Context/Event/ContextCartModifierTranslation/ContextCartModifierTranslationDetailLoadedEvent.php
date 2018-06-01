<?php declare(strict_types=1);

namespace Shopware\Core\Application\Context\Event\ContextCartModifierTranslation;

use Shopware\Core\Application\Context\Collection\ContextCartModifierTranslationDetailCollection;
use Shopware\Core\Application\Context\Event\ContextCartModifier\ContextCartModifierBasicLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class ContextCartModifierTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'context_cart_modifier_translation.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var ContextCartModifierTranslationDetailCollection
     */
    protected $contextCartModifierTranslations;

    public function __construct(ContextCartModifierTranslationDetailCollection $ContextCartModifierTranslations, Context $context)
    {
        $this->context = $context;
        $this->contextCartModifierTranslations = $ContextCartModifierTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getContextCartModifierTranslations(): ContextCartModifierTranslationDetailCollection
    {
        return $this->contextCartModifierTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->contextCartModifierTranslations->getContextCartModifiers()->count() > 0) {
            $events[] = new ContextCartModifierBasicLoadedEvent($this->contextCartModifierTranslations->getContextCartModifiers(), $this->context);
        }
        if ($this->contextCartModifierTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->contextCartModifierTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
