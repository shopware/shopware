<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidDocumentGeneratorTypeException extends ShopwareHttpException
{
    protected $code = 'INVALID-DOCUMENT-GENERATOR-TYPE';

    public function __construct(string $type, $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Unable to find a document generator with type "%s"', $type);
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
