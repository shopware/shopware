<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class DocumentGenerationException extends ShopwareHttpException
{
    protected $code = 'DOCUMENT-GENERATION';

    public function __construct(string $message = '', $code = 0)
    {
        $message = sprintf('Unable to generate document. ' . $message);
        parent::__construct($message, $code);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return $this->code;
    }
}
