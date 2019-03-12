<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidSortingDirectionException extends ShopwareHttpException
{
    public function __construct(string $direction, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('The given sorting direction "%s" is invalid.', $direction);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
