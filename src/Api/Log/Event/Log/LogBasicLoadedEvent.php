<?php declare(strict_types=1);

namespace Shopware\Api\Log\Event\Log;

use Shopware\Api\Log\Collection\LogBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class LogBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'log.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var LogBasicCollection
     */
    protected $logs;

    public function __construct(LogBasicCollection $logs, ShopContext $context)
    {
        $this->context = $context;
        $this->logs = $logs;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getLogs(): LogBasicCollection
    {
        return $this->logs;
    }
}
