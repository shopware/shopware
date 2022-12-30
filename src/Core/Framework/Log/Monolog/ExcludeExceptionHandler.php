<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log\Monolog;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\HandlerInterface;

/**
 * @package core
 */
class ExcludeExceptionHandler extends AbstractHandler
{
    private HandlerInterface $handler;

    /**
     * @var array<int, string>
     */
    private array $excludeExceptionList;

    /**
     * @internal
     *
     * @param array<int, string> $excludeExceptionList
     */
    public function __construct(HandlerInterface $handler, array $excludeExceptionList)
    {
        parent::__construct();
        $this->excludeExceptionList = $excludeExceptionList;
        $this->handler = $handler;
    }

    /**
     * {@inheritdoc}
     */
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
