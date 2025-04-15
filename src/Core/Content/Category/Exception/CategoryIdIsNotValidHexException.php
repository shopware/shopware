<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('discovery')]
class CategoryIdIsNotValidHexException extends ShopwareHttpException
{
    public function __construct(string $categoryId)
    {
        parent::__construct(
            'Category ID "{{ categoryId }}" is not a valid hexadecimal value.',
            ['categoryId' => $categoryId]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__CATEGORY_ID_IS_NOT_HEX';
    }
}
