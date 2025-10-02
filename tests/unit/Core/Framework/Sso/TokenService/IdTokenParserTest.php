<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Sso\TokenService;

use Lcobucci\JWT\Validator as ValidatorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\SsoException;
use Shopware\Core\Framework\Sso\TokenService\IdTokenParser;
use Shopware\Core\Framework\Sso\TokenService\PublicKeyLoader;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\Sso\Helper\FakeTokenGenerator;
use Shopware\Tests\Unit\Core\Framework\Sso\TokenService\_fixtures\JwksIds;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\ClockInterface;
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
    public function testParse(): void
    {
        $idToken = (new FakeTokenGenerator())->generate(JwksIds::KEY_ID_TWO);

        $idTokenParser = new IdTokenParser(
            $this->createPublicKeyLoader(),
            $this->createLoginConfigService(),
            $this->createClock()
        );

        $validationResults = [
            'first issued by check' => true,
            'first signed and loose valid check' => true,
        ];

        $validator = $this->createValidator($validationResults);

        $validatorProperty = (new \ReflectionClass(IdTokenParser::class))->getProperty('validator');
        $validatorProperty->setAccessible(true);
        $validatorProperty->setValue($idTokenParser, $validator);

        $result = $idTokenParser->parse($idToken);

        static::assertSame('fake-subject', $result->sub);
        static::assertSame('fake@email.com', $result->email);
        static::assertInstanceOf(\DateTimeImmutable::class, $result->expiry);
    }

    /**
     * @param array<string, bool> $validationResults
     */
    #[DataProvider('invalidTokenTestCases')]
    public function testParseWithInvalidTokenShouldThrowException(array $validationResults): void
    {
        $idToken = (new FakeTokenGenerator())->generate(JwksIds::KEY_ID_TWO);

        $idTokenParser = new IdTokenParser(
            $this->createPublicKeyLoader(),
            $this->createLoginConfigService(),
            $this->createClock()
        );

        $validator = $this->createValidator($validationResults);

        $validatorProperty = (new \ReflectionClass(IdTokenParser::class))->getProperty('validator');
        $validatorProperty->setAccessible(true);
        $validatorProperty->setValue($idTokenParser, $validator);

        $this->expectExceptionObject(new SsoException(0, '0', 'The id token is invalid'));
        $idTokenParser->parse($idToken);
    }

    /**
     * @return array<string, array<string, array<string, bool>>>
     */
    public static function invalidTokenTestCases(): array
    {
        return [
            'IssuedBy is invalid' => [
                'validationResults' => [
                    'first issued by check' => false,
                    'second issued by check' => false,
                ],
            ],
            'SignedWith and LooseValidAt are invalid' => [
                'validationResults' => [
                    'first issued by check' => false,
                    'second issued by check' => true,
                    'first signed and loose valid check' => false,
                    'recursion first issued by check' => false,
                    'recursion second issued by check' => true,
                    'final signed and loose valid check' => false,
                ],
            ],
        ];
    }

    /**
     * @param array<string, bool> $results
     */
    private function createValidator(array $results): ValidatorInterface
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturnOnConsecutiveCalls(...\array_values($results));

        return $validator;
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
        $jwks = \file_get_contents(__DIR__ . '/_fixtures/jwks.json');
        static::assertIsString($jwks);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($jwks);

        $client = $this->createMock(HttpClientInterface::class);
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
            $this->createMock(RouterInterface::class)
        );
    }

    private function createClock(): ClockInterface
    {
        return $this->createMock(ClockInterface::class);
    }
}
