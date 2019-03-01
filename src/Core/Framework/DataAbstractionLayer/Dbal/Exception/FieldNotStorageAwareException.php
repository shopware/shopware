<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class FieldNotStorageAwareException extends ShopwareHttpException
{
    public function __construct(string $field, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('The field %s must implement the StorageAware interface to be accessible.', $field);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
