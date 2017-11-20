<?php declare(strict_types=1);

namespace Shopware\Log\Event\Log;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Log\Collection\LogBasicCollection;

class LogBasicLoadedEvent extends NestedEvent
{
    const NAME = 'log.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var LogBasicCollection
     */
    protected $logs;

    public function __construct(LogBasicCollection $logs, TranslationContext $context)
    {
        $this->context = $context;
        $this->logs = $logs;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getLogs(): LogBasicCollection
    {
        return $this->logs;
    }
}
