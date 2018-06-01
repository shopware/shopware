<?php declare(strict_types=1);

namespace Shopware\Application\Context\Event\ContextCartModifierTranslation;

use Shopware\Application\Context\Collection\ContextCartModifierTranslationDetailCollection;
use Shopware\Application\Context\Event\ContextCartModifier\ContextCartModifierBasicLoadedEvent;
use Shopware\Framework\Context;
use Shopware\Application\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ContextCartModifierTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'context_cart_modifier_translation.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
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
