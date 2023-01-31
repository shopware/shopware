<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class SetGroupNotFoundException extends ShopwareHttpException
{
    public function __construct(string $groupId)
    {
        parent::__construct('Promotion SetGroup "{{ id }}" has not been found!', ['id' => $groupId]);
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__PROMOTION_SETGROUP_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
