<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Authentication;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceUserException;
use Shopware\Core\Framework\Api\Context\ShopApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Authentication\AbstractAuthenticationProvider;
use Shopware\Core\Framework\Store\Authentication\AuthenticationProvider;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class AuthenticationProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItReturnsTheCorrectHeader(): void
    {
        $userId = Uuid::randomHex();
        $storeToken = Uuid::randomHex();

        $data = [
            [
                'id' => $userId,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => 'foobar',
                'password' => 'asdasdasdasd',
                'firstName' => 'Foo',
                'lastName' => 'Bar',
                'email' => 'foo@bar.com',
                'storeToken' => $storeToken,
            ],
        ];

        $this->getContainer()->get('user.repository')->create($data, Context::createDefaultContext());

        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $authenticationHeaderProvider = $this->getAuthProvider();

        $header = $authenticationHeaderProvider->getUserStoreToken($context);

        static::assertEquals($storeToken, $header);
    }

    public function testInvalidContext(): void
    {
        $authenticationHeaderProvider = $this->getAuthProvider();

        static::expectException(InvalidContextSourceException::class);

        $authenticationHeaderProvider->getUserStoreToken(Context::createDefaultContext(new ShopApiSource('')));
    }

    public function testUserIdIsNull(): void
    {
        $authenticationHeaderProvider = $this->getAuthProvider();

        static::expectException(InvalidContextSourceUserException::class);

        $authenticationHeaderProvider->getUserStoreToken(new Context(new AdminApiSource(null)));
    }

    public function testUserIsMissing(): void
    {
        $authenticationHeaderProvider = $this->getAuthProvider();

        static::assertNull($authenticationHeaderProvider->getUserStoreToken(new Context(new AdminApiSource(Uuid::randomHex()))));
    }

    public function testUserWithoutToken(): void
    {
        $userId = Uuid::randomHex();

        $data = [
            [
                'id' => $userId,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => 'foobar',
                'password' => 'asdasdasdasd',
                'firstName' => 'Foo',
                'lastName' => 'Bar',
                'email' => 'foo@bar.com',
            ],
        ];

        $this->getContainer()->get('user.repository')->create($data, Context::createDefaultContext());

        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $authenticationHeaderProvider = $this->getAuthProvider();

        static::assertNull($authenticationHeaderProvider->getUserStoreToken($context));
    }

    public function testSystemContextUsesUserToken(): void
    {
        $userId = Uuid::randomHex();

        $data = [
            [
                'id' => $userId,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => 'foobar',
                'password' => 'asdasdasdasd',
                'firstName' => 'Foo',
                'lastName' => 'Bar',
                'email' => 'foo@bar.com',
            ],
        ];

        $this->getContainer()->get('user.repository')->create($data, Context::createDefaultContext());

        $authenticationHeaderProvider = $this->getAuthProvider();

        static::assertNull($authenticationHeaderProvider->getUserStoreToken(Context::createDefaultContext()));
    }

    public function testSystemContextWithoutToken(): void
    {
        $userId = Uuid::randomHex();

        $data = [
            [
                'id' => $userId,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => 'foobar',
                'password' => 'asdasdasdasd',
                'firstName' => 'Foo',
                'lastName' => 'Bar',
                'email' => 'foo@bar.com',
            ],
        ];

        $this->getContainer()->get('user.repository')->create($data, Context::createDefaultContext());

        $authenticationHeaderProvider = $this->getAuthProvider();

        static::assertNull($authenticationHeaderProvider->getUserStoreToken(Context::createDefaultContext()));
    }

    /**
     * because AbstractAuthenticationProvider service is deprecated it is inlined by symfony. We have to create an own service here
     */
    private function getAuthProvider(): AbstractAuthenticationProvider
    {
        return new AuthenticationProvider(
            $this->getContainer()->get(StoreRequestOptionsProvider::class)
        );
    }
}
