<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ApiException extends HttpException
{
    public const API_INVALID_SYNC_CRITERIA_EXCEPTION = 'API_INVALID_SYNC_CRITERIA_EXCEPTION';
    public const API_RESOLVER_NOT_FOUND_EXCEPTION = 'API_RESOLVER_NOT_FOUND_EXCEPTION';
    public const API_INVALID_SYNC_OPERATION_EXCEPTION = 'FRAMEWORK__INVALID_SYNC_OPERATION';

    public static function invalidSyncCriteriaException(string $operationKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_INVALID_SYNC_CRITERIA_EXCEPTION,
            \sprintf('Sync operation %s, with action "delete", requires a criteria with at least one filter and can only be applied for mapping entities', $operationKey)
        );
    }

    public static function invalidSyncOperationException(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_INVALID_SYNC_OPERATION_EXCEPTION,
            $message
        );
    }

    public static function resolverNotFoundException(string $key): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::API_RESOLVER_NOT_FOUND_EXCEPTION,
            \sprintf('Foreign key resolver for key %s not found', $key)
        );
    }
}
