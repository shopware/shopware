<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\ScriptException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[Package('core')]
class ScriptExecutionFailedException extends ScriptException
{
    public const ERROR_CODE = 'FRAMEWORK_SCRIPT_EXECUTION_FAILED';

    public function __construct(
        string $hook,
        string $scriptName,
        \Throwable $previous
    ) {
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $errorCode = self::ERROR_CODE;

        $rootException = $previous->getPrevious();
        if ($rootException instanceof HttpExceptionInterface) {
            $statusCode = $rootException->getStatusCode();
        }

        if ($rootException instanceof ShopwareHttpException) {
            $errorCode = $rootException->getErrorCode();
        }

        parent::__construct(
            $statusCode,
            $errorCode,
            sprintf(
                'Execution of script "%s" for Hook "%s" failed with message: %s',
                $scriptName,
                $hook,
                $previous->getMessage()
            ),
            [],
            $previous
        );
    }
}
