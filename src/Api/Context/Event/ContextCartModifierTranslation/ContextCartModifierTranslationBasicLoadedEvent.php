<?php declare(strict_types=1);

namespace Shopware\Api\Context\Event\ContextCartModifierTranslation;

use Shopware\Api\Context\Collection\ContextCartModifierTranslationBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class ContextCartModifierTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'context_cart_modifier_translation.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ContextCartModifierTranslationBasicCollection
     */
    protected $ContextCartModifierTranslations;

    public function __construct(ContextCartModifierTranslationBasicCollection $ContextCartModifierTranslations, ShopContext $context)
    {
        $this->context = $context;
        $this->ContextCartModifierTranslations = $ContextCartModifierTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getContextCartModifierTranslations(): ContextCartModifierTranslationBasicCollection
    {
        return $this->ContextCartModifierTranslations;
    }
}
