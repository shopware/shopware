<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class CurrencyNotFoundHttpException extends HttpException
{
    public const CODE = 4001;

    public function __construct(string $id)
    {
        parent::__construct(400, sprintf('Currency with id %s not found', $id), null, [], self::CODE);
    }
}
