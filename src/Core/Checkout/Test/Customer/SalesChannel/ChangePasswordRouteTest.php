<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;

/**
 * @group store-api
 */
class ChangePasswordRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $contextToken;

    /**
     * @var string
     */
    private $customerId;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $this->contextToken = $response['contextToken'];

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);
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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayNotHasKey('errors', $response);

        static::assertNotEmpty($response['contextToken']);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $this->email,
                    'password' => 'foooware',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayNotHasKey('errors', $response);
        static::assertArrayHasKey('contextToken', $response);
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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $oldContextExists = $this->getContainer()->get(SalesChannelContextPersister::class)->load($this->contextToken, $this->ids->get('sales-channel'));

        static::assertEmpty($oldContextExists);

        // Token is replaced
        static::assertNotEquals($this->contextToken, $response['contextToken']);

        $newContextExists = $this->getContainer()->get(SalesChannelContextPersister::class)->load($response['contextToken'], $this->ids->get('sales-channel'), $this->customerId);

        static::assertNotEmpty($newContextExists);
    }
}
