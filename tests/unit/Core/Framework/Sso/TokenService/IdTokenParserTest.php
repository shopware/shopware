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
    /**
     * The key pair shared with the other JWT unit tests: valid-jwks.json publishes the public half of private.pem.
     */
    private const KEY_FIXTURES = __DIR__ . '/../../JWT/_fixtures';

    private const KEY_ID = 'ibvOgtMeMhihwgJvEw9yxXOs1YX07H34';

    private const ISSUER = 'https://base.url';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-01-01 12:00:00');
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

        return Builder::new(new JoseEncoder(), ChainedFormatter::default())
            ->withHeader('kid', self::KEY_ID)
            ->issuedBy($issuer)
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->relatedTo('fake-subject')
            ->withClaim('email', 'fake@email.com')
            ->getToken(new Sha256(), InMemory::file(self::KEY_FIXTURES . '/private.pem'))
            ->toString();
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
        $response->method('getContent')->willReturn((string) file_get_contents(self::KEY_FIXTURES . '/valid-jwks.json'));

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
