<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use DocumentException::guestNotAuthenticated instead
 */
#[Package('after-sales')]
class InvalidDocumentRendererException extends DocumentException
{
    public function __construct(string $type)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::DOCUMENT_INVALID_RENDERER_TYPE,
            \sprintf('Unable to find a document renderer with type "%s"', $type),
            [
                '$type' => $type,
            ],
        );
    }
}
