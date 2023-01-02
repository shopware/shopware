<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class DocumentNumberAlreadyExistsException extends ShopwareHttpException
{
    public function __construct(?string $number)
    {
        parent::__construct('Document number {{number}} has already been allocated.', [
            'number' => $number,
        ]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'DOCUMENT__NUMBER_ALREADY_EXISTS';
    }
}
