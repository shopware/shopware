<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - will be removed, use domain specific xmlParsingExceptions instead
 */
#[Package('services-settings')]
class XmlParsingException extends ShopwareHttpException
{
    public function __construct(
        string $xmlFile,
        string $message
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'domain specific xmlParsingExceptions')
        );

        parent::__construct(
            'Unable to parse file "{{ file }}". Message: {{ message }}',
            ['file' => $xmlFile, 'message' => $message]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'domain specific xmlParsingExceptions')
        );

        return 'SYSTEM__XML_PARSE_ERROR';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'domain specific xmlParsingExceptions')
        );

        return Response::HTTP_BAD_REQUEST;
    }
}
