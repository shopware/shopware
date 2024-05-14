<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log\Monolog;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Psr\Log\LogLevel;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[Package('core')]
class ErrorCodeLogLevelHandler extends AbstractHandler
{
    /**
     * @param array<string, value-of<Level::NAMES>|LogLevel::*|'Debug'|'Info'|'Notice'|'Warning'|'Error'|'Critical'|'Alert'|'Emergency'> $errorCodesToLogLevel
     *
     * @internal
     */
    public function __construct(
        private readonly HandlerInterface $handler,
        private readonly array $errorCodesToLogLevel
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(LogRecord $record): bool
    {
        if (!isset($record->context['exception']) || !\is_object($record->context['exception'])) {
            return $this->handler->handle($record);
        }

        $exception = $record->context['exception'];

        if ($exception instanceof HandlerFailedException) {
            // Symfony wraps the original exception, so we peak into the wrapped,
            // to see if it was configured to a specific log level,
            // but we don't want to silence all HandlerFailedExceptions
            $exception = $exception->getPrevious();
        }

        if ($exception instanceof ShopwareHttpException
            && \array_key_exists($exception->getErrorCode(), $this->errorCodesToLogLevel)) {
            $level = Level::fromName($this->errorCodesToLogLevel[$exception->getErrorCode()]);

            $record = new LogRecord(
                $record->datetime,
                $record->channel,
                $level,
                $record->message,
                $record->context,
                $record->extra,
                $record->formatted
            );
        }

        return $this->handler->handle($record);
    }
}
