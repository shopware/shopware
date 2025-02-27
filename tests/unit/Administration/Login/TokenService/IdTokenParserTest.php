<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Login\TokenService;

use Lcobucci\JWT\Validator as ValidatorInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Administration\Login\TokenService\IdTokenParser;
use Shopware\Administration\Login\TokenService\PublicKeyLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Administration\Login\Helper\FakeTokenGenerator;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('after-sales')]
class IdTokenParserTest extends TestCase
{
    public function testParse(): void
    {
        $idToken = (new FakeTokenGenerator())->generate('b16b070d-28e4-4759-9c51-d43730dda8fa');

        $idTokenParser = new IdTokenParser(
            $this->createPublicKeyLoader(),
            $this->createLoginConfigService(),
            $this->createClock()
        );

        $validator = $this->createValidator(true);

        $validatorProperty = (new \ReflectionClass(IdTokenParser::class))->getProperty('validator');
        $validatorProperty->setAccessible(true);
        $validatorProperty->setValue($idTokenParser, $validator);

        $result = $idTokenParser->parse($idToken);

        static::assertSame('fake-subject', $result->sub);
        static::assertSame('fake@email.com', $result->email);
        static::assertInstanceOf(\DateTimeImmutable::class, $result->expiry);
    }

    public function testParseWithInvalidTokenShouldThrowException(): void
    {
        $idToken = (new FakeTokenGenerator())->generate('b16b070d-28e4-4759-9c51-d43730dda8fa');

        $idTokenParser = new IdTokenParser(
            $this->createPublicKeyLoader(),
            $this->createLoginConfigService(),
            $this->createClock()
        );

        $validator = $this->createValidator(false);

        $validatorProperty = (new \ReflectionClass(IdTokenParser::class))->getProperty('validator');
        $validatorProperty->setAccessible(true);
        $validatorProperty->setValue($idTokenParser, $validator);

        try {
            $idTokenParser->parse($idToken);
        } catch (LoginException $loginException) {
            static::assertSame('The id token is invalid', $loginException->getMessage());
            static::assertSame('LOGIN__INVALID_ID_TOKEN', $loginException->getErrorCode());
            static::assertSame(Response::HTTP_UNAUTHORIZED, $loginException->getStatusCode());

            return;
        }

        static::fail('LoginException should have been thrown');
    }

    private function createValidator(bool $isValid): ValidatorInterface
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn($isValid);

        return $validator;
    }

    private function createPublicKeyLoader(): PublicKeyLoader
    {
        return new PublicKeyLoader(
            $this->createClient(),
            $this->createLoginConfigService(),
            $this->createCache()
        );
    }

    private function createCache(): AdapterInterface
    {
        $cache = $this->createMock(AdapterInterface::class);
        $createCacheItem = \Closure::bind(
            static function () {
                $item = new CacheItem();
                $item->key = 'cache_key';
                $item->isHit = false;
                $item->value = null;
                $item->unpack();

                return $item;
            },
            null,
            CacheItem::class
        );

        $cacheItem = $createCacheItem();
        $emptyCacheItem = $createCacheItem('any', null, false);

        $cache->method('getItem')->willReturnOnConsecutiveCalls($cacheItem, $emptyCacheItem);

        return $cache;
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
            ],
            'app.path',
            '/admin'
        );
    }

    private function createClock(): ClockInterface
    {
        return $this->createMock(ClockInterface::class);
    }
}
