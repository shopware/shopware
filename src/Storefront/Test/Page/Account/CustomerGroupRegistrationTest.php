<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Exception\CustomerGroupRegistrationConfigurationNotFound;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPage;
use Shopware\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class CustomerGroupRegistrationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    private TestDataCollection $ids;

    private SalesChannelContext $salesChannel;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();
        $this->salesChannel = $this->createSalesChannelContext();
    }

    public function test404(): void
    {
        static::expectException(CustomerGroupRegistrationConfigurationNotFound::class);
        $request = new Request();
        $request->attributes->set('customerGroupId', Defaults::LANGUAGE_SYSTEM);

        $this->getPageLoader()->load($request, $this->salesChannel);
    }

    public function testGetConfiguration(): void
    {
        $customerGroupRepository = $this->getContainer()->get('customer_group.repository');
        $customerGroupRepository->create([
            [
                'id' => $this->ids->create('group'),
                'name' => 'foo',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationSalesChannels' => [['id' => $this->salesChannel->getSalesChannel()->getId()]],
            ],
        ], Context::createDefaultContext());

        $request = new Request();
        $request->attributes->set('customerGroupId', $this->ids->get('group'));

        $page = $this->getPageLoader()->load($request, $this->salesChannel);
        static::assertInstanceOf(CustomerGroupRegistrationPage::class, $page);
        static::assertSame($this->ids->get('group'), $page->getGroup()->getId());
        static::assertSame('test', $page->getGroup()->getRegistrationTitle());
    }

    protected function getPageLoader(): CustomerGroupRegistrationPageLoader
    {
        return $this->getContainer()->get(CustomerGroupRegistrationPageLoader::class);
    }
}
