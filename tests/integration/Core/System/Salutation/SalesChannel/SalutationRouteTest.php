<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Salutation\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('store-api')]
#[Package('checkout')]
class SalutationRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
    }

    public function testSalutation(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/salutation',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(3, $response['total']);
        static::assertArrayHasKey('salutationKey', $response['elements'][0]);
        static::assertArrayHasKey('displayName', $response['elements'][0]);
        static::assertArrayHasKey('letterName', $response['elements'][0]);
    }

    public function testIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/salutation',
                [
                    'includes' => [
                        'salutation' => ['id'],
                    ],
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(3, $response['total']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayNotHasKey('displayName', $response['elements'][0]);
        static::assertArrayNotHasKey('letterName', $response['elements'][0]);
    }

    public function testProtectedAssociationCannotBeExposedThroughIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/salutation',
                [
                    'limit' => 1,
                    'associations' => [
                        'customers' => [],
                    ],
                    'includes' => [
                        'salutation' => ['id', 'customers.id'],
                    ],
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertArrayNotHasKey('elements', $response);
        static::assertSame('FRAMEWORK__ACCESSOR_NOT_ALLOWED', $response['errors'][0]['code']);
    }

    public function testLimit(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/salutation',
                [
                    'limit' => 1,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayHasKey('displayName', $response['elements'][0]);
        static::assertArrayHasKey('letterName', $response['elements'][0]);
    }

    public function testCustomerFromAnotherSalesChannelCannotBeExposed(): void
    {
        $customer = (new CustomerBuilder($this->ids, 'customer', salesChannelId: TestDefaults::SALES_CHANNEL))
            ->add('boundSalesChannelId', TestDefaults::SALES_CHANNEL);

        static::getContainer()->get('customer.repository')->create([$customer->build()], Context::createDefaultContext());

        $this->browser->request('POST', '/store-api/salutation', [
            'ids' => [$this->ids->get('salutation')],
            'associations' => [
                'customers' => [],
            ],
            'includes' => [
                'salutation' => ['salutationKey', 'customers.x'],
                'customer' => ['firstName'],
            ],
        ]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertArrayNotHasKey('elements', $response);
        static::assertSame('FRAMEWORK__ACCESSOR_NOT_ALLOWED', $response['errors'][0]['code']);
    }
}
