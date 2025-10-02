<?php declare(strict_types=1);

namespace Shopware\Core\Framework\JWT\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class JWTStruct extends Struct
{
    /**
     * Issuer of the JWT
     */
    public ?string $iss = null;

    /**
     * Audience for which the JWT is intended
     */
    public ?string $aud = null;

    /**
     * Expiration time of the JWT (as Unix timestamp)
     */
    public ?\DateTimeImmutable $exp = null;

    /**
     * Issued at time of the JWT (as Unix timestamp)
     */
    public ?\DateTimeImmutable $iat = null;

    /**
     * The unique identifier for the JWT (JTI)
     */
    public ?string $jti = null;

    /**
     * Not before time of the JWT (as Unix timestamp)
     */
    public ?\DateTimeImmutable $nbf = null;

    /**
     * Subject of the JWT (the user identifier)
     */
    public ?string $sub = null;

    /**
     * Scopes associated with the JWT
     *
     * @var list<string>
     */
    public array $scopes = [];
}
