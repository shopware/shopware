<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\ShopwareException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TouchpointNotFoundException extends HttpException implements ShopwareException
{
    public function __construct(int $code = 0, Throwable $previous = null)
    {
        $message = 'The touchpoint was not found.';

        parent::__construct(Response::HTTP_PRECONDITION_FAILED, $message, $previous, [], $code);
    }
}
