<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CategoryNotFoundException extends ShopwareHttpException
{
    public const CODE = 400000;

    public function __construct(string $productId)
    {
        parent::__construct(sprintf('Category for id %s not found', $productId), self::CODE);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
