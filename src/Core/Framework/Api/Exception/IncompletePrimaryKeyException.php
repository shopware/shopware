<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class IncompletePrimaryKeyException extends ShopwareHttpException
{
    public function __construct(array $primaryKeyFields, $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf(
            'The primary key consists of %d fields. Please provide values for the following fields: %s',
            \count($primaryKeyFields),
            implode(', ', $primaryKeyFields)
        );

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
