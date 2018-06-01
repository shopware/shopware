<?php declare(strict_types=1);

namespace Shopware\System\Log\Event\Log;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Log\Collection\LogBasicCollection;

class LogBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'log.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var LogBasicCollection
     */
    protected $logs;

    public function __construct(LogBasicCollection $logs, Context $context)
    {
        $this->context = $context;
        $this->logs = $logs;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getLogs(): LogBasicCollection
    {
        return $this->logs;
    }
}
