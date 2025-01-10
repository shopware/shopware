<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - reason:remove-exception - Will be removed. Use \Shopware\Core\System\SalesChannel\SalesChannelException::taxNotFound instead
 */
#[Package('checkout')]
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
