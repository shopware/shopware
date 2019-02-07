<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class FilterNotFoundException extends ShopwareHttpException
{
    protected $code = 'FILTER-NOT-FOUND';

    public function __construct(string $filterName, string $class, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The %s filter was not found in %s', $filterName, $class);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
