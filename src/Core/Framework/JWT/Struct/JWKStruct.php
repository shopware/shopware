<?php declare(strict_types=1);

namespace Shopware\Core\Framework\JWT\Struct;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @phpstan-type JSONWebKey array{kty: string, kid: string, use: string, alg: string, n: string, e: string}
 */
#[Package('checkout')]
class JWKStruct
{
    #[NotNull, NotBlank, Type('string')]
    public readonly string $kty;

    #[NotNull, NotBlank, Type('string')]
    public readonly string $kid;

    #[NotNull, NotBlank, Type('string')]
    public readonly string $use;

    #[NotNull, NotBlank, Type('string')]
    public readonly string $alg;

    #[NotNull, NotBlank, Type('string')]
    public readonly string $n;

    #[NotNull, NotBlank, Type('string')]
    public readonly string $e;

    /**
     * @param JSONWebKey $data
     */
    public function __construct(array $data)
    {
        $this->kty = $data['kty'];
        $this->kid = $data['kid'];
        $this->use = $data['use'];
        $this->alg = $data['alg'];
        $this->n = $data['n'];
        $this->e = $data['e'];
    }
}
