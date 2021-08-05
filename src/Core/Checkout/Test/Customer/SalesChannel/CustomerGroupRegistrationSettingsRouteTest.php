<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;

/**
 * @group store-api
 */
class CustomerGroupRegistrationSettingsRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->assignSalesChannelContext($this->browser);
    }

    public function testInvalidId(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/customer-group-registration/config/' . Defaults::LANGUAGE_SYSTEM
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(404, $this->browser->getResponse()->getStatusCode());

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_GROUP_REGISTRATION_NOT_FOUND', $response['errors'][0]['code']);
    }

    public function testWithValidConfig(): void
    {
        $customerGroupRepository = $this->getContainer()->get('customer_group.repository');
        $customerGroupRepository->create([
            [
                'id' => $this->ids->create('group'),
                'name' => 'foo',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationSalesChannels' => [['id' => $this->getSalesChannelApiSalesChannelId()]],
            ],
        ], $this->ids->getContext());

        $this->browser
            ->request(
                'GET',
                '/store-api/customer-group-registration/config/' . $this->ids->get('group')
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        static::assertSame($this->ids->get('group'), $response['id']);
        static::assertSame('test', $response['registrationTitle']);
    }
}
