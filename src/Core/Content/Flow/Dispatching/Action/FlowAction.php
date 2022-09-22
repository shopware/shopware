<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 implements EventSubscriberInterface will be removed, the FlowAction will be not implements anymore
 */
abstract class FlowAction implements EventSubscriberInterface
{
    /**
     * @return array<int, string>
     */
    abstract public function requirements(): array;

    /**
     * @deprecated tag:v6.5.0 - Will be removed, made an abstract function handleFlow instead
     */
    abstract public function handle(FlowEvent $event): void;

    /**
     * @deprecated tag:v6.5.0 - Will be become an abstract function
     */
    public function handleFlow(StorableFlow $flow): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        if ($flow->getFlowEvent()) {
            $this->handle($flow->getFlowEvent());
        }
    }

    abstract public static function getName(): string;
}
