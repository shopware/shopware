<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Doctrine\DBAL\Exception as DBALException;
use Shopware\Core\Content\Flow\Dispatching\TransactionFailedException;
use Shopware\Core\Content\Flow\Exception\CustomTriggerByNameNotFoundException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('after-sales')]
class FlowException extends HttpException
{
    final public const METHOD_NOT_COMPATIBLE = 'METHOD_NOT_COMPATIBLE';
    final public const FLOW_ACTION_TRANSACTION_ABORTED = 'FLOW_ACTION_TRANSACTION_ABORTED';
    final public const FLOW_ACTION_TRANSACTION_COMMIT_FAILED = 'FLOW_ACTION_TRANSACTION_COMMIT_FAILED';
    final public const FLOW_ACTION_TRANSACTION_UNCAUGHT_EXCEPTION = 'FLOW_ACTION_TRANSACTION_UNCAUGHT_EXCEPTION';
    final public const CUSTOM_TRIGGER_BY_NAME_NOT_FOUND = 'FLOW_ACTION_CUSTOM_TRIGGER_BY_NAME_NOT_FOUND';

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     */
    public static function customTriggerByNameNotFound(string $eventName): self|CustomTriggerByNameNotFoundException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new CustomTriggerByNameNotFoundException($eventName);
        }

        return new self(
            Response::HTTP_NOT_FOUND,
            self::CUSTOM_TRIGGER_BY_NAME_NOT_FOUND,
            'The provided event name {{ eventName }} is invalid or uninstalled and no custom trigger could be found.',
            ['eventName' => $eventName]
        );
    }

    public static function methodNotCompatible(string $method, string $class): FlowException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::METHOD_NOT_COMPATIBLE,
            'Method {{ method }} is not compatible for {{ class }} class',
            ['method' => $method, 'class' => $class]
        );
    }

    public static function transactionFailed(\Throwable $previous): self
    {
        return match (true) {
            $previous instanceof TransactionFailedException => new self(
                Response::HTTP_BAD_REQUEST,
                self::FLOW_ACTION_TRANSACTION_ABORTED,
                'Flow action transaction was aborted and rolled back. Exception: ' . $previous->getMessage(),
                [],
                $previous,
            ),
            $previous instanceof DBALException => new self(
                Response::HTTP_BAD_REQUEST,
                self::FLOW_ACTION_TRANSACTION_COMMIT_FAILED,
                'Flow action transaction could not be committed and was rolled back. Exception: ' . $previous->getMessage(),
                [],
                $previous,
            ),
            default => new self(
                Response::HTTP_BAD_REQUEST,
                self::FLOW_ACTION_TRANSACTION_UNCAUGHT_EXCEPTION,
                'Flow action transaction could not be completed and was rolled back. An uncaught exception occurred: ' . $previous->getMessage(),
                [],
                $previous,
            ),
        };
    }
}
