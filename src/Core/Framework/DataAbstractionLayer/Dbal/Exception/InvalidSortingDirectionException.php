<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class InvalidSortingDirectionException extends ShopwareHttpException
{
    public function __construct(string $direction)
    {
        parent::__construct(
            'The given sort direction "{{ direction }}" is invalid.',
            ['direction' => $direction]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_SORT_DIRECTION';
    }
}
