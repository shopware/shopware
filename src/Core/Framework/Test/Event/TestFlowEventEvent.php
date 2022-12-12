<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\FlowEventAware;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class TestFlowEventEvent extends Event implements FlowEventAware
{
    public const EVENT_NAME = 'test.flow_event';

    /**
     * @var string
     */
    protected $name = self::EVENT_NAME;

    /**
     * @var Context
     */
    protected $context;

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
