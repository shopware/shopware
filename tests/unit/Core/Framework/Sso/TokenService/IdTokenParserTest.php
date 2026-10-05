<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Sso\TokenService;

use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\SsoException;
use Shopware\Core\Framework\Sso\TokenService\IdTokenParser;
use Shopware\Core\Framework\Sso\TokenService\PublicKeyLoader;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IdTokenParser::class)]
class IdTokenParserTest extends TestCase
{
    private const KEY_ID = '742be0d0-038a-4f1a-b70d-d1ecabc2af05';

    private const ISSUER = 'https://base.url';

    private MockClock $clock;

    private string $privateKey;

    private string $jwks;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-01-01 12:00:00');

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($key);
        static::assertTrue(openssl_pkey_export($key, $privateKey));
        static::assertIsString($privateKey);
        $this->privateKey = $privateKey;

        $details = openssl_pkey_get_details($key);
        static::assertIsArray($details);
        $this->jwks = json_encode(['keys' => [[
            'use' => 'sig',
            'kty' => 'RSA',
            'kid' => self::KEY_ID,
            'alg' => 'RS256',
            'n' => self::base64UrlEncode($details['rsa']['n']),
            'e' => self::base64UrlEncode($details['rsa']['e']),
        ]]], \JSON_THROW_ON_ERROR);
    }

    public function testParse(): void
    {
        $idTokenParser = new IdTokenParser(
            $this->createPublicKeyLoader(),
            $this->createLoginConfigService(),
            $this->clock
        );

        $result = $idTokenParser->parse($this->createIdToken(self::ISSUER));

        static::assertSame('fake-subject', $result->sub);
        static::assertSame('fake@email.com', $result->email);
        static::assertEquals($this->clock->now()->modify('+1 hour'), $result->expiry);
    }

    public function testParseWithInvalidTokenShouldThrowException(): void
    {
        $idTokenParser = new IdTokenParser(
            $this->createPublicKeyLoader(),
            $this->createLoginConfigService(),
            $this->clock
        );

        $this->expectExceptionObject(new SsoException(0, '0', 'The id token is invalid'));
        $idTokenParser->parse($this->createIdToken('https://other.issuer'));
    }

    /**
     * @param non-empty-string $issuer
     *
     * @return non-empty-string
     */
    private function createIdToken(string $issuer): string
    {
        $now = $this->clock->now();
        $privateKey = $this->privateKey;
        static::assertNotSame('', $privateKey);

        return Builder::new(new JoseEncoder(), ChainedFormatter::default())
            ->withHeader('kid', self::KEY_ID)
            ->issuedBy($issuer)
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->relatedTo('fake-subject')
            ->withClaim('email', 'fake@email.com')
            ->getToken(new Sha256(), InMemory::plainText($privateKey))
            ->toString();
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function createPublicKeyLoader(): PublicKeyLoader
    {
        return new PublicKeyLoader(
            $this->createClient(),
            $this->createLoginConfigService(),
            new ArrayAdapter()
        );
    }

    private function createClient(): HttpClientInterface
    {
        $response = static::createStub(ResponseInterface::class);
        $response->method('getContent')->willReturn($this->jwks);

        $client = static::createStub(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        return $client;
    }

    private function createLoginConfigService(): LoginConfigService
    {
        return new LoginConfigService(
            [
                'use_default' => false,
                'client_id' => Uuid::randomHex(),
                'client_secret' => Uuid::randomHex(),
                'redirect_uri' => 'https://redirect.to',
                'base_url' => 'https://base.url',
                'authorize_path' => '/authorize',
                'token_path' => '/token',
                'jwks_path' => '/json.json',
                'scope' => 'scope',
                'register_url' => 'https://register.url',
            ],
            static::createStub(RouterInterface::class)
        );
    }
}
