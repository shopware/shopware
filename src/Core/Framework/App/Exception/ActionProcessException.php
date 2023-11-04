<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ActionProcessException extends ShopwareHttpException
{
    public function __construct(
        private readonly string $actionId,
        string $errorMessage,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            'The synchronous action process was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            ['errorMessage' => $errorMessage],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__SYNC_ACTION_PROCESS_INTERRUPTED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getActionId(): string
    {
        return $this->actionId;
    }
}
