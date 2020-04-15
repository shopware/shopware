<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\GoogleAccountCredential;
use Shopware\Core\Content\GoogleShopping\Exception\InvalidGoogleIdToken;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use function Flag\skipTestNext6050;

class GoogleAccountCredentialTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    protected function setUp(): void
    {
        skipTestNext6050($this);
    }

    public function testGetIdTokenFails(): void
    {
        $this->expectException(InvalidGoogleIdToken::class);

        $rawCredentials = $this->getSampleCredential();
        $rawCredentials['id_token'] = 'INVALID_FORMAT';

        $cred = new GoogleAccountCredential($rawCredentials);

        $cred->getIdTokenParts();
    }

    public function testGetIdTokenParts(): void
    {
        $rawCredentials = $this->getSampleCredential();

        $cred = new GoogleAccountCredential($rawCredentials);

        $idTokenParts = $cred->getIdTokenParts();

        static::assertEquals($idTokenParts, ['name' => 'John Doe', 'email' => 'john.doe@example.com']);
    }

    public function testNormalizeSuccess(): void
    {
        $rawCredentials = $this->getSampleCredential();

        $cred = new GoogleAccountCredential($rawCredentials);

        $normalize = $cred->normalize();

        static::assertNotEmpty($normalize);
        static::assertEquals(count($rawCredentials), count($normalize));
        static::assertEquals($cred->getIdToken(), $normalize['id_token']);
        static::assertEquals($cred->getScope(), $normalize['scope']);
        static::assertEquals($cred->getRefreshToken(), $normalize['refresh_token']);
        static::assertEquals($cred->getAccessToken(), $normalize['access_token']);
        static::assertEquals($cred->getCreated(), $normalize['created']);
        static::assertEquals($cred->getExpiresIn(), $normalize['expires_in']);
    }
}
