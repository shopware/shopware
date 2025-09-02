<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Cookie;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class CookieException extends HttpException
{
    final public const NOT_ALLOWED_PROPERTY_ASSIGNMENT = 'CONTENT__COOKIE_NOT_ALLOWED_PROPERTY_ASSIGNMENT';

    public static function notAllowedPropertyAssignment(string $propertyToBeAssigned, string $alreadyAssignedProperty): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::NOT_ALLOWED_PROPERTY_ASSIGNMENT,
            'Property "{{ propertyToBeAssigned }}" cannot be set, as "{{ alreadyAssignedProperty }}" is already set.',
            ['propertyToBeAssigned' => $propertyToBeAssigned, 'alreadyAssignedProperty' => $alreadyAssignedProperty],
        );
    }
}
