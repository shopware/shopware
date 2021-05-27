<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class TestFlowBusinessEvent extends Event implements BusinessEventInterface
{
    public const EVENT_NAME = 'test.flow_business_event';

    protected string $name = self::EVENT_NAME;

    protected Context $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }
}
