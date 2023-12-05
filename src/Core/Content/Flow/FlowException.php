<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow;

use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('services-settings')]
class FlowException extends HttpException
{
    final public const METHOD_NOT_COMPATIBLE = 'METHOD_NOT_COMPATIBLE';
    final public const ERRORS_DURING_EXECUTION = 'ERRORS_DURING_EXECUTION';

    public static function methodNotCompatible(string $method, string $class): FlowException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::METHOD_NOT_COMPATIBLE,
            'Method {{ method }} is not compatible for {{ class }} class',
            ['method' => $method, 'class' => $class]
        );
    }

    /**
     * @param array<string, \Throwable> $exceptionsByFlowId
     * @param array<string, string> $flowNamesByFlowId
     */
    public static function errorsDuringExecution(array $exceptionsByFlowId, array $flowNamesByFlowId): FlowException
    {
        $errorMessage = "Could not execute flows:\n";
        foreach ($exceptionsByFlowId as $flowId => $exception) {
            $sequenceString = '';
            if ($exception instanceof ExecuteSequenceException) {
                $sequenceString = sprintf('Sequence id: %s ', $exception->getSequenceId());
            }

            $errorMessage .= sprintf(
                "Could not execute flow with error message: Flow name: \"%s\" Flow id: \"%s\" %sError Message: %s Error Code: \"%s\"\n",
                $flowNamesByFlowId[$flowId],
                $flowId,
                $sequenceString,
                $exception->getMessage(),
                $exception->getCode(),
            );
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ERRORS_DURING_EXECUTION,
            $errorMessage,
            [
                'exceptionsByFlowId' => $exceptionsByFlowId,
                'flowNamesByFlowId' => array_filter(
                    $flowNamesByFlowId,
                    fn (string $flowId) => \array_key_exists($flowId, $exceptionsByFlowId),
                    \ARRAY_FILTER_USE_KEY,
                ),
            ],
        );
    }
}
