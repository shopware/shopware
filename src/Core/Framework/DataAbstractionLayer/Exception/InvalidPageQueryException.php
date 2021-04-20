<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidPageQueryException extends ShopwareHttpException
{
    public function __construct($page, ?\Throwable $previous = null)
    {
        parent::__construct('The page parameter must be a positive integer. Given: {{ page }}', ['page' => $page], $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_PAGE_QUERY';
    }
}
