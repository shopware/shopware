<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class AssociationNotFoundException extends ShopwareHttpException
{
    public function __construct(string $field, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Can not find association by name {{ association }}',
            ['association' => $field],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__ASSOCIATION_NOT_FOUND';
    }
}
