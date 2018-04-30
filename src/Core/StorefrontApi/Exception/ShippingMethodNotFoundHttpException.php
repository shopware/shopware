<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ShippingMethodNotFoundHttpException extends HttpException
{
    public const CODE = 4008;

    public function __construct(string $id)
    {
        parent::__construct(400, sprintf('Shipping method with id %s not found', $id), null, [], self::CODE);
    }
}
