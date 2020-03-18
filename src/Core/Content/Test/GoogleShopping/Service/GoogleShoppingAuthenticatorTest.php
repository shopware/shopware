<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Client\GoogleShoppingClient;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\GoogleAccountCredential;
use Shopware\Core\Content\GoogleShopping\Exception\InvalidGoogleAuthorizationCodeException;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingAuthenticator;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use function Flag\skipTestNext6050;

class GoogleShoppingAuthenticatorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    protected function setUp(): void
    {
        skipTestNext6050($this);
    }

    public function testAuthorizeFail(): void
    {
        $invalidCode = 'INVALID.AUTHORIZATION.CODE';

        $this->expectException(InvalidGoogleAuthorizationCodeException::class);
        $mockClient = $this->createMock(GoogleShoppingClient::class);
        $mockClient->expects(static::once())->method('fetchAccessTokenWithAuthCode')->with($invalidCode)->willReturn(['error' => 'Invalid Auth Code']);

        $authenticatorService = new GoogleShoppingAuthenticator($mockClient);

        $authenticatorService->authorize($invalidCode);
    }

    public function testAuthorizeSuccess(): void
    {
        $validCode = 'VALID.AUTHORIZATION.CODE';

        $rawCred = $this->getSampleCredential();

        $mockClient = $this->createMock(GoogleShoppingClient::class);
        $mockClient->expects(static::once())->method('fetchAccessTokenWithAuthCode')->with($validCode)->willReturn($rawCred);

        $authenticatorService = new GoogleShoppingAuthenticator($mockClient);

        $cred = $authenticatorService->authorize($validCode);

        static::assertEquals($cred, new GoogleAccountCredential($rawCred));
    }
}
