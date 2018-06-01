<?php declare(strict_types=1);

namespace Shopware\Core\System\Log\Event\Log;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Log\Collection\LogBasicCollection;

class LogBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'log.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
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
