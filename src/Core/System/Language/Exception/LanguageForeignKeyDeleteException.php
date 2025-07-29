<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('fundamentals@discovery')]
/**
 * @deprecated tag:v6.8.0 - Will be removed, as the exception is no longer needed, languages now also throw RestrictDeleteViolationException
 * @see RestrictDeleteViolationException is now thrown instead
 */
class LanguageForeignKeyDeleteException extends ShopwareHttpException
{
    public function __construct(?\Throwable $e = null)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(
                __CLASS__,
                'v6.8.0.0',
                RestrictDeleteViolationException::class
            )
        );

        parent::__construct(
            'The language cannot be deleted because foreign key constraints exist.',
            [],
            $e
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(
                __CLASS__,
                'v6.8.0.0',
                RestrictDeleteViolationException::class
            )
        );

        return 'FRAMEWORK__LANGUAGE_FOREIGN_KEY_DELETE';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(
                __CLASS__,
                'v6.8.0.0',
                RestrictDeleteViolationException::class
            )
        );

        return Response::HTTP_BAD_REQUEST;
    }
}
