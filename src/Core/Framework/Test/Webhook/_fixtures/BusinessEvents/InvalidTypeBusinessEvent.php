<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @internal
 */
class InvalidTypeBusinessEvent implements FlowEventAware
{
    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('invalid', new InvalidEventType());
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public function getInvalid(): string
    {
        return 'invalid';
    }
}
