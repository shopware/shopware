<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CategoryNotFoundException extends ShopwareHttpException
{
    public const CODE = 400000;

    public function __construct(string $categoryId, int $code = self::CODE, \Throwable $previous = null)
    {
        $message = sprintf('Category for id %s not found', $categoryId);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
