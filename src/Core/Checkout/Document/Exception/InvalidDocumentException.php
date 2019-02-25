<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidDocumentException extends ShopwareHttpException
{
    protected $code = 'INVALID-DOCUMENT-ID';

    public function __construct(string $documentId, $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('The document with id "%s" is invalid or could not be found.', $documentId);
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
