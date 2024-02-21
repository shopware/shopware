<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Feature;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class FeatureException extends HttpException
{
    final public const FEATURE_NOT_REGISTERED = 'FRAMEWORK__FEATURE_NOT_REGISTERED';
    final public const MAJOR_FEATURE_CANNOT_BE_TOGGLE = 'FRAMEWORK__MAJOR_FEATURE_CANNOT_BE_TOGGLE';
    final public const FEATURE_CANNOT_BE_TOGGLE = 'FRAMEWORK__FEATURE_CANNOT_BE_TOGGLE';
    final public const FEATURE_ERROR = 'FRAMEWORK__FEATURE_ERROR';

    public static function featureNotRegistered(string $feature): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FEATURE_NOT_REGISTERED,
            'Feature "{{ feature }}" is not registered.',
            ['feature' => $feature]
        );
    }

    public static function featureCannotBeToggled(string $feature): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FEATURE_CANNOT_BE_TOGGLE,
            'Feature "{{ feature }}" cannot be toggled.',
            ['feature' => $feature]
        );
    }

    public static function cannotToggleMajor(string $feature): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MAJOR_FEATURE_CANNOT_BE_TOGGLE,
            'Feature "{{ feature }}" is major so it cannot be toggled.',
            ['feature' => $feature]
        );
    }

    public static function error(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FEATURE_ERROR,
            $message
        );
    }
}
