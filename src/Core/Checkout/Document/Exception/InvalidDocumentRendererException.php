<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed, use DocumentException::invalidDocumentRendererType instead
 */
#[Package('checkout')]
class InvalidDocumentRendererException extends DocumentException
{
    public function __construct(string $type)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'DocumentException::documentAlreadyExists')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DOCUMENT_RENDERER_TYPE,
            'Unable to find a document renderer with type "{{type}}"',
            ['type' => $type]
        );
    }
}
