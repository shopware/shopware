<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
/**
 * @deprecated tag:v6.8.0 - Will be removed, as the exception is no longer needed, languages now also throw RestrictDeleteViolationException
 * @see RestrictDeleteViolationException is now thrown instead
 */
class LanguageOfOrderDeleteException extends ShopwareHttpException
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

        parent::__construct('The language is still linked in some orders.', [], $e);
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

        return 'CHECKOUT__LANGUAGE_OF_ORDER_DELETE';
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
