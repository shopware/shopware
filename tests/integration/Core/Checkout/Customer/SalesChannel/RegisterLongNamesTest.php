<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
class RegisterLongNamesTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $ids = new IdsCollection();
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $ids->create('sales-channel'),
        ]);
    }

    public function testRegistrationWithLongNames(): void
    {
        $longFirstName = str_repeat('Max', 34);
        $longLastName = str_repeat('Mustermann', 10);

        $registrationData = [
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => $longFirstName,
            'lastName' => $longLastName,
            'password' => '12345678',
            'email' => 'long-name@example.com',
            'storefrontUrl' => 'http://localhost',
            'billingAddress' => [
                'firstName' => $longFirstName,
                'lastName' => $longLastName,
                'countryId' => $this->getValidCountryId(),
                'street' => 'Examplestreet 11',
                'zipcode' => '48441',
                'city' => 'Cologne',
            ],
        ];

        $this->browser->request(
            'POST',
            '/store-api/account/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($registrationData, \JSON_THROW_ON_ERROR)
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('id', $response);
        static::assertSame('customer', $response['apiAlias']);

        static::assertSame($longFirstName, $response['firstName']);
        static::assertSame($longLastName, $response['lastName']);

        $contextToken = $this->browser->getResponse()->headers->get('sw-context-token');

        $this->browser->request(
            'POST',
            '/store-api/account/list-address',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_SW_CONTEXT_TOKEN' => $contextToken,
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('elements', (array) $response);
        $addresses = $response['elements'];
        static::assertCount(1, $addresses);
        static::assertSame($longFirstName, $addresses[0]['firstName']);
        static::assertSame($longLastName, $addresses[0]['lastName']);
    }

    public function testRegistrationWithMaximumNameLengths(): void
    {
        $maxFirstName = str_repeat('M', 255);
        $maxLastName = str_repeat('L', 255);

        $registrationData = [
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => $maxFirstName,
            'lastName' => $maxLastName,
            'password' => '12345678',
            'email' => 'max-length@example.com',
            'storefrontUrl' => 'http://localhost',
            'billingAddress' => [
                'firstName' => $maxFirstName,
                'lastName' => $maxLastName,
                'countryId' => $this->getValidCountryId(),
                'street' => 'Examplestreet 11',
                'zipcode' => '48441',
                'city' => 'Cologne',
            ],
        ];

        $this->browser->request(
            'POST',
            '/store-api/account/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($registrationData, \JSON_THROW_ON_ERROR)
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('id', $response);

        static::assertSame($maxFirstName, $response['firstName']);
        static::assertSame($maxLastName, $response['lastName']);
    }

    public function testRegistrationWithTooLongNamesFails(): void
    {
        $tooLongFirstName = str_repeat('T', 256);

        $registrationData = [
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => $tooLongFirstName,
            'lastName' => 'Mustermann',
            'password' => '12345678',
            'email' => 'too-long@example.com',
            'storefrontUrl' => 'http://localhost',
            'billingAddress' => [
                'firstName' => $tooLongFirstName,
                'lastName' => 'Mustermann',
                'countryId' => $this->getValidCountryId(),
                'street' => 'Examplestreet 11',
                'zipcode' => '48441',
                'city' => 'Cologne',
            ],
        ];

        $this->browser->request(
            'POST',
            '/store-api/account/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($registrationData, \JSON_THROW_ON_ERROR)
        );

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
    }
}
