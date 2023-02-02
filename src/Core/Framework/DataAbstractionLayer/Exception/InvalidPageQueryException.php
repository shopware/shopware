<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class InvalidPageQueryException extends ShopwareHttpException
{
    /**
     * @param mixed $page
     */
    public function __construct($page)
    {
        parent::__construct('The page parameter must be a positive integer. Given: {{ page }}', ['page' => $page]);
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
