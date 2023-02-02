<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class DocumentGenerationException extends ShopwareHttpException
{
    public function __construct(string $message = '')
    {
        $message = sprintf('Unable to generate document. ' . $message);
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'DOCUMENT__GENERATION_ERROR';
    }
}
