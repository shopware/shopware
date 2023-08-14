<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use RoutingException::languageNotFound instead
 */
#[Package('core')]
class LanguageNotFoundException extends RoutingException
{
    final public const LANGUAGE_NOT_FOUND_ERROR = 'FRAMEWORK__LANGUAGE_NOT_FOUND';

    public function __construct(?string $languageId)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use RoutingException::languageNotFound instead')
        );

        parent::__construct(
            Response::HTTP_PRECONDITION_FAILED,
            self::LANGUAGE_NOT_FOUND_ERROR,
            'The language "{{ languageId }}" was not found.',
            ['languageId' => $languageId]
        );
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use RoutingException::languageNotFound instead')
        );

        return Response::HTTP_PRECONDITION_FAILED;
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use RoutingException::languageNotFound instead')
        );

        return self::LANGUAGE_NOT_FOUND_ERROR;
    }
}
