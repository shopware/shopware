<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0.
 */
class TestBusinessEvent extends Event implements BusinessEventInterface
{
    public const EVENT_NAME = 'test.business_event';

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
