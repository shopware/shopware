<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Framework\Event\FlowEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal (flag:FEATURE_NEXT_8225)
 */
abstract class FlowAction implements EventSubscriberInterface
{
    abstract public function requirements(): array;

    abstract public function handle(FlowEvent $event): void;

    abstract public static function getName(): string;
}
