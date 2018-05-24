<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Exception;

use Shopware\Framework\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NotLoggedInCustomerException extends \Exception implements HttpExceptionInterface
{
    public const CODE = 4005;

    public function __construct()
    {
        parent::__construct('No logged in customer detected', self::CODE);
    }

    public function getHttpException(): \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
    {
        return new HttpException(Response::HTTP_FORBIDDEN, $this->getMessage(), $this, [], self::CODE);
    }
}
