<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso\Exceptions;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SsoUserNotFoundException extends SsoException
{
    public const SSO_LOGIN_USER_NOT_FOUND = 'SSO_LOGIN__USER_NOT_FOUND';

    private ?string $email;

    public function __construct(
        ?string $email = null,
    ) {
        parent::__construct(
            Response::HTTP_UNAUTHORIZED,
            self::SSO_LOGIN_USER_NOT_FOUND,
            'User not found',
        );

        $this->email = $email;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }
}
