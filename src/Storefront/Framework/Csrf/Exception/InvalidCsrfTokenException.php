<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf\Exception;

use Shopware\Core\Framework\Feature;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @deprecated tag:v6.5.0 - InvalidCsrfTokenException will be removed as the csrf system will be removed in favor for the samesite approach
 */
class InvalidCsrfTokenException extends HttpException
{
    public function __construct()
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        parent::__construct(Response::HTTP_FORBIDDEN, 'The provided CSRF token is not valid');
    }
}
