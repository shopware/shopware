<?php declare(strict_types=1);

namespace Shopware\Api\Context\Event\ContextCartModifierTranslation;

use Shopware\Api\Context\Collection\ContextCartModifierTranslationBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ContextCartModifierTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'context_cart_modifier_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ContextCartModifierTranslationBasicCollection
     */
    protected $contextCartModifierTranslations;

    public function __construct(ContextCartModifierTranslationBasicCollection $contextCartModifierTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->contextCartModifierTranslations = $contextCartModifierTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getContextCartModifierTranslations(): ContextCartModifierTranslationBasicCollection
    {
        return $this->contextCartModifierTranslations;
    }
}
