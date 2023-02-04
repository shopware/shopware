<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log\Monolog;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ExcludeExceptionHandler extends AbstractHandler
{
    /**
     * @internal
     *
     * @param array<int, string> $excludeExceptionList
     */
    public function __construct(
        private readonly HandlerInterface $handler,
        private readonly array $excludeExceptionList
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(LogRecord $record): bool
    {
        if (
            isset($record->context['exception'])
            && \is_object($record->context['exception'])
            && \in_array($record->context['exception']::class, $this->excludeExceptionList, true)
        ) {
            return true;
        }

        return $this->handler->handle($record);
    }
}
