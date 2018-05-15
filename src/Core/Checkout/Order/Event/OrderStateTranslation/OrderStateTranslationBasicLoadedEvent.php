<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderStateTranslation;

use Shopware\Checkout\Order\Collection\OrderStateTranslationBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class OrderStateTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_state_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var OrderStateTranslationBasicCollection
     */
    protected $orderStateTranslations;

    public function __construct(OrderStateTranslationBasicCollection $orderStateTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->orderStateTranslations = $orderStateTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getOrderStateTranslations(): OrderStateTranslationBasicCollection
    {
        return $this->orderStateTranslations;
    }
}
