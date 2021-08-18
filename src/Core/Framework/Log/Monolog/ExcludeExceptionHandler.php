<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log\Monolog;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\HandlerInterface;

class ExcludeExceptionHandler extends AbstractHandler
{
    private HandlerInterface $handler;

    private array $excludeExceptionList;

    public function __construct(HandlerInterface $handler, array $excludeExceptionList)
    {
        parent::__construct();
        $this->handler = $handler;
        $this->excludeExceptionList = $excludeExceptionList;
    }

    public function handle(array $record): bool
    {
        if (
            isset($record['context']['exception'])
            && \is_object($record['context']['exception'])
            && \in_array(\get_class($record['context']['exception']), $this->excludeExceptionList, true)
        ) {
            return true;
        }

        return $this->handler->handle($record);
    }
}
