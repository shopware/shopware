<?php declare(strict_types=1);

namespace Shopware\Application\Context\Event\ContextCartModifierTranslation;

use Shopware\Application\Context\Collection\ContextCartModifierTranslationBasicCollection;
use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;

class ContextCartModifierTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'context_cart_modifier_translation.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var ContextCartModifierTranslationBasicCollection
     */
    protected $contextCartModifierTranslations;

    public function __construct(ContextCartModifierTranslationBasicCollection $contextCartModifierTranslations, Context $context)
    {
        $this->context = $context;
        $this->contextCartModifierTranslations = $contextCartModifierTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getContextCartModifierTranslations(): ContextCartModifierTranslationBasicCollection
    {
        return $this->contextCartModifierTranslations;
    }
}
