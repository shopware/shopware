<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed, use DocumentException::generationError instead
 */
#[Package('checkout')]
class DocumentGenerationException extends DocumentException
{
    public function __construct(string $message = '')
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'DocumentException::documentAlreadyExists')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::GENERATION_ERROR,
            'Unable to generate document. ' . $message
        );
    }
}
