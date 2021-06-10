<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Salutation\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;

/**
 * @group store-api
 */
class SalutationRouteTest extends TestCase
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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(3, $response['total']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayNotHasKey('displayName', $response['elements'][0]);
        static::assertArrayNotHasKey('letterName', $response['elements'][0]);
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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(1, $response['total']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayHasKey('displayName', $response['elements'][0]);
        static::assertArrayHasKey('letterName', $response['elements'][0]);
    }
}
