<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @group store-api
 */
#[Package('customer-order')]
class ChangePasswordRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private string $email;

    private string $contextToken;

    private string $customerId;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);

        $this->email = Uuid::randomHex() . '@example.com';
        $this->customerId = $this->createCustomer('shopware', $this->email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $this->email,
                    'password' => 'shopware',
                ]
            );

        $response = $this->browser->getResponse();

        $this->contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($this->contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->contextToken);
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-password',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::IS_BLANK_ERROR', $response['errors'][0]['code']);
    }

    public function testChangeInvalidPassword(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-password',
                [
                    'password' => 'foooware',
                    'newPassword' => 'foooware',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::CUSTOMER_PASSWORD_NOT_CORRECT', $response['errors'][0]['code']);
    }

    public function testChangeAndLogin(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-password',
                [
                    'password' => 'shopware',
                    'newPassword' => 'foooware',
                    'newPasswordConfirm' => 'foooware',
                ]
            );

        $response = $this->browser->getResponse();

        $responseContent = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('errors', $responseContent);

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $this->email,
                    'password' => 'foooware',
                ]
            );

        $response = $this->browser->getResponse();

        $responseContent = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('errors', $responseContent);

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);
    }

    public function testContextTokenIsReplacedAfterChangingPassword(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-password',
                [
                    'password' => 'shopware',
                    'newPassword' => 'foooware',
                    'newPasswordConfirm' => 'foooware',
                ]
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $oldContextExists = $this->getContainer()->get(SalesChannelContextPersister::class)->load($this->contextToken, $this->ids->get('sales-channel'));

        static::assertEmpty($oldContextExists);

        // Token is replaced
        static::assertNotEquals($this->contextToken, $contextToken);

        $newContextExists = $this->getContainer()->get(SalesChannelContextPersister::class)->load($contextToken, $this->ids->get('sales-channel'), $this->customerId);

        static::assertNotEmpty($newContextExists);
    }
}
