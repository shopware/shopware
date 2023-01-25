<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class InvalidDocumentException extends ShopwareHttpException
{
    public function __construct(string $documentId)
    {
        $message = sprintf('The document with id "%s" is invalid or could not be found.', $documentId);
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'DOCUMENT__INVALID_DOCUMENT_ID';
    }
}
