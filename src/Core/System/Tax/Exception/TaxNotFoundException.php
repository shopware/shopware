<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class TaxNotFoundException extends ShopwareHttpException
{
    public function __construct(string $taxId)
    {
        parent::__construct(
            'Tax with id "{{ id }}" not found.',
            ['id' => $taxId]
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__TAX_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
