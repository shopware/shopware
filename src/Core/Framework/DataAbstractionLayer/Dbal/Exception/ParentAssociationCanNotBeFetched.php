<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ParentAssociationCanNotBeFetched extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'It is not possible to read the parent association directly. Please read the parents via a separate call over the repository'
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PARENT_ASSOCIATION_CAN_NOT_BE_FETCHED';
    }
}
