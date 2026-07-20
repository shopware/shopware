<?php declare(strict_types=1);

namespace Shopware\Core\Framework\JWT\Struct;

use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ExtendableInterface;
use Shopware\Core\Framework\Struct\ExtendableTrait;
use Shopware\Core\Framework\Struct\VariablesAccessTrait;

#[Package('checkout')]
class JWTStruct implements ExtendableInterface
{
    use ExtendableTrait;
    use VariablesAccessTrait;

    /**
     * Issuer of the JWT
     */
    public ?string $iss = null;

    /**
     * Audience for which the JWT is intended
     *
     * @var list<non-empty-string>|null
     */
    public ?array $aud = null;

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

    /**
     * @param array<string, mixed> $data
     */
    final public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (!property_exists($this, $key)) {
                throw JWTException::invalidJwt('Property ' . $key . ' does not exist in JWTStruct');
            }

            // @phpstan-ignore-next-line property.dynamicName does not understand that we check for property existence above
            $this->$key = $value;
        }
    }
}
