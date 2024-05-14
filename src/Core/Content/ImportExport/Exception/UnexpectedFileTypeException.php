<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use ImportExportException::unexpectedFileType instead
 */
#[Package('services-settings')]
class UnexpectedFileTypeException extends ShopwareHttpException
{
    public function __construct(
        ?string $givenType,
        string $expectedType
    ) {
        parent::__construct(
            'Given file does not match MIME-Type for selected profile. Given: {{ given }}. Expected: {{ expected }}',
            ['given' => $givenType, 'expected' => $expectedType]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'ImportExportException::unexpectedFileType')
        );

        return 'CONTENT__IMPORT_FILE_HAS_UNEXPECTED_TYPE';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'ImportExportException::unexpectedFileType')
        );

        return Response::HTTP_BAD_REQUEST;
    }
}
