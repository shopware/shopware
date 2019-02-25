<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class DocumentGenerationException extends ShopwareHttpException
{
    protected $code = 'DOCUMENT-GENERATION';

    public function __construct(string $message = '', $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Unable to generate document. ' . $message);
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
