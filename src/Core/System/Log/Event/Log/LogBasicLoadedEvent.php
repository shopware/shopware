<?php declare(strict_types=1);

namespace Shopware\System\Log\Event\Log;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Log\Collection\LogBasicCollection;

class LogBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'log.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var LogBasicCollection
     */
    protected $logs;

    public function __construct(LogBasicCollection $logs, ApplicationContext $context)
    {
        $this->context = $context;
        $this->logs = $logs;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getLogs(): LogBasicCollection
    {
        return $this->logs;
    }
}
