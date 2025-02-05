<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Exception;

use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed, use DocumentException::invalidDocumentGeneratorType instead
 */
#[Package('after-sales')]
class InvalidFileGeneratorTypeException extends DocumentException
{
    public function __construct(string $type)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'DOCUMENT__INVALID_FILE_GENERATOR_TYPE',
            'Unable to find a file generator with type "{{type}}"',
            ['type' => $type]
        );
    }
}
