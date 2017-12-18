<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderStateTranslation;

use Shopware\Api\Order\Collection\OrderStateTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class OrderStateTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_state_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var OrderStateTranslationBasicCollection
     */
    protected $orderStateTranslations;

    public function __construct(OrderStateTranslationBasicCollection $orderStateTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->orderStateTranslations = $orderStateTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getOrderStateTranslations(): OrderStateTranslationBasicCollection
    {
        return $this->orderStateTranslations;
    }
}
