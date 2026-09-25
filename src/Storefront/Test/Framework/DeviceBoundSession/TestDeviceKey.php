<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\DeviceBoundSession;

use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;
use Shopware\Core\Framework\Log\Package;

/**
 * Plays the browser's part of DBSC: holds a P-256 key and signs `dbsc+jwt` proofs.
 *
 * @internal
 */
#[Package('framework')]
final class TestDeviceKey
{
    /**
     * @var non-empty-string
     */
    private readonly string $privateKey;

    /**
     * @var array{kty: string, crv: string, x: string, y: string}
     */
    private readonly array $jwk;

    public function __construct()
    {
        $key = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        \assert($key !== false);

        openssl_pkey_export($key, $privateKey);
        \assert(\is_string($privateKey) && $privateKey !== '');
        $this->privateKey = $privateKey;

        $details = openssl_pkey_get_details($key);
        \assert(\is_array($details));

        $this->jwk = [
            'kty' => 'EC',
            'crv' => 'P-256',
            // OpenSSL drops leading zero bytes of the coordinates, JWK requires the full 32 bytes
            'x' => self::base64Url(str_pad($details['ec']['x'], 32, "\0", \STR_PAD_LEFT)),
            'y' => self::base64Url(str_pad($details['ec']['y'], 32, "\0", \STR_PAD_LEFT)),
        ];
    }

    /**
     * @return array{kty: string, crv: string, x: string, y: string}
     */
    public function jwk(): array
    {
        return $this->jwk;
    }

    public function signRegistration(string $challenge): string
    {
        return $this->sign($challenge, ['jwk' => $this->jwk]);
    }

    public function signRefresh(string $challenge): string
    {
        return $this->sign($challenge, []);
    }

    /**
     * @param array<string, mixed> $headers
     */
    public function sign(string $challenge, array $headers, string $type = 'dbsc+jwt'): string
    {
        $builder = Builder::new(new JoseEncoder(), ChainedFormatter::default())
            ->withHeader('typ', $type);

        foreach ($headers as $name => $value) {
            \assert($name !== '');
            $builder = $builder->withHeader($name, $value);
        }

        \assert($challenge !== '');

        return $builder->identifiedBy($challenge)
            ->getToken(new Sha256(), InMemory::plainText($this->privateKey))
            ->toString();
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
