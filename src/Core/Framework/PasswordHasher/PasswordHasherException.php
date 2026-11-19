<?php declare(strict_types=1);

namespace Shopware\Core\Framework\PasswordHasher;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PasswordHasherException extends HttpException
{
    public const UNSUPPORTED_ALGORITHM = 'FRAMEWORK__PASSWORD_HASHER_UNSUPPORTED_ALGORITHM';
    public const UNKNOWN_SCOPE = 'FRAMEWORK__PASSWORD_HASHER_UNKNOWN_SCOPE';

    /**
     * @param list<string> $availableAlgorithms
     */
    public static function unsupportedAlgorithm(string $algorithm, array $availableAlgorithms): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNSUPPORTED_ALGORITHM,
            'The password hashing algorithm "{{ algorithm }}" is not supported by this PHP installation. Available algorithms: {{ availableAlgorithms }}',
            ['algorithm' => $algorithm, 'availableAlgorithms' => implode(', ', $availableAlgorithms)]
        );
    }

    /**
     * @param list<string> $availableScopes
     */
    public static function unknownScope(string $scope, array $availableScopes): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNKNOWN_SCOPE,
            'No password hasher is registered for scope "{{ scope }}". Available scopes: {{ availableScopes }}',
            ['scope' => $scope, 'availableScopes' => implode(', ', $availableScopes)]
        );
    }
}
