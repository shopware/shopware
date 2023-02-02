<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidCsrfTokenException extends HttpException
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_FORBIDDEN, 'The provided CSRF token is not valid');
    }
}
