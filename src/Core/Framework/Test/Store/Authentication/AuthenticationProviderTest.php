<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Authentication;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceUserException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Authentication\AbstractAuthenticationProvider;
use Shopware\Core\Framework\Store\Exception\StoreTokenMissingException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class AuthenticationProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItReturnsTheCorrectHeader(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);

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

        /** @var AbstractAuthenticationProvider $authenticationHeaderProvider */
        $authenticationHeaderProvider = $this->getContainer()->get(AbstractAuthenticationProvider::class);

        $header = $authenticationHeaderProvider->getUserStoreToken($context);

        static::assertEquals($storeToken, $header);
    }

    public function testInvalidContext(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);

        /** @var AbstractAuthenticationProvider $authenticationHeaderProvider */
        $authenticationHeaderProvider = $this->getContainer()->get(AbstractAuthenticationProvider::class);

        static::expectException(InvalidContextSourceException::class);

        $authenticationHeaderProvider->getUserStoreToken(Context::createDefaultContext());
    }

    public function testUserIdIsNull(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);

        /** @var AbstractAuthenticationProvider $authenticationHeaderProvider */
        $authenticationHeaderProvider = $this->getContainer()->get(AbstractAuthenticationProvider::class);

        static::expectException(InvalidContextSourceUserException::class);

        $authenticationHeaderProvider->getUserStoreToken(new Context(new AdminApiSource(null)));
    }

    public function testUserIsMissing(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);

        /** @var AbstractAuthenticationProvider $authenticationHeaderProvider */
        $authenticationHeaderProvider = $this->getContainer()->get(AbstractAuthenticationProvider::class);

        static::expectException(StoreTokenMissingException::class);

        $authenticationHeaderProvider->getUserStoreToken(new Context(new AdminApiSource(Uuid::randomHex())));
    }

    public function testUserWithoutToken(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);

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

        /** @var AbstractAuthenticationProvider $authenticationHeaderProvider */
        $authenticationHeaderProvider = $this->getContainer()->get(AbstractAuthenticationProvider::class);

        static::expectException(StoreTokenMissingException::class);
        $authenticationHeaderProvider->getUserStoreToken($context);
    }
}
