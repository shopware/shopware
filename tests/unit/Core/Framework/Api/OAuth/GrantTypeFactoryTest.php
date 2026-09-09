<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\GrantTypeFactory;
use Shopware\Core\Framework\Api\OAuth\ShopwareAuthCodeGrantType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;
use Shopware\Core\Framework\Sso\ShopwareGrantType;
use Shopware\Core\Framework\Sso\ShopwarePasswordGrantType;
use Shopware\Core\Framework\Sso\ShopwareRefreshTokenGrantType;
use Shopware\Core\Framework\Sso\TokenService\ExternalTokenService;
use Shopware\Core\Framework\Sso\TokenService\IdTokenParser;
use Shopware\Core\Framework\Sso\TokenService\PublicKeyLoader;
use Shopware\Core\Framework\Sso\UserService\UserService;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GrantTypeFactory::class)]
class GrantTypeFactoryTest extends TestCase
{
    public function testCreatesAllGrantTypesOfTheAdminApi(): void
    {
        $clock = new NativeClock();
        $loginConfigService = static::createStub(LoginConfigService::class);
        $externalTokenService = new ExternalTokenService(static::createStub(HttpClientInterface::class), $loginConfigService);
        $userService = new UserService(
            static::createStub(Connection::class),
            new IdTokenParser(
                new PublicKeyLoader(static::createStub(HttpClientInterface::class), $loginConfigService, new ArrayAdapter()),
                $loginConfigService,
                $clock
            ),
            StaticEntityRepository::of(UserCollection::class, []),
            $externalTokenService,
            $clock,
        );

        $factory = new GrantTypeFactory(
            static::createStub(UserRepositoryInterface::class),
            static::createStub(RefreshTokenRepositoryInterface::class),
            static::createStub(AuthCodeRepositoryInterface::class),
            $userService,
            $externalTokenService,
            $clock,
        );

        $grantTypes = $factory->createGrantTypes();

        $identifiers = array_map(static fn ($grantType) => $grantType->getIdentifier(), $grantTypes);
        static::assertSame(
            ['password', 'refresh_token', 'client_credentials', ShopwareGrantType::TYPE, 'authorization_code'],
            $identifiers
        );

        static::assertInstanceOf(ShopwarePasswordGrantType::class, $grantTypes[0]);
        static::assertInstanceOf(ShopwareRefreshTokenGrantType::class, $grantTypes[1]);
        static::assertInstanceOf(ShopwareGrantType::class, $grantTypes[3]);
        static::assertInstanceOf(ShopwareAuthCodeGrantType::class, $grantTypes[4]);
    }
}
