<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ScriptExecutionFailedException extends ShopwareHttpException
{
    private readonly ?\Throwable $rootException;

    public function __construct(
        string $hook,
        string $scriptName,
        \Throwable $previous
    ) {
        $this->rootException = $previous->getPrevious();
        parent::__construct(sprintf(
            'Execution of script "%s" for Hook "%s" failed with message: %s',
            $scriptName,
            $hook,
            $previous->getMessage()
        ), [], $previous);
    }

    public function getStatusCode(): int
    {
        if ($this->rootException instanceof ShopwareHttpException) {
            return $this->rootException->getStatusCode();
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    public function getErrorCode(): string
    {
        if ($this->rootException instanceof ShopwareHttpException) {
            return $this->rootException->getErrorCode();
        }

        return 'FRAMEWORK_SCRIPT_EXECUTION_FAILED';
    }
}
