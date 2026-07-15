<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Garan;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class GaranLabelRouteTest extends TestCase
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

    public function testGaranLabelIsRenderedForCompleteProduct(): void
    {
        $product = (new ProductBuilder($this->ids, 'garan-product'))
            ->price(10)
            ->visibility($this->ids->get('sales-channel'))
            ->manufacturer('acme')
            ->build();
        $product['manufacturerNumber'] = 'ACME-123';
        $product['guaranteeMonths'] = 36;
        $product['guaranteeConfirmed'] = true;

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        $this->browser->request('GET', '/store-api/product/' . $this->ids->get('garan-product') . '/garan-label');

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsString($response['svg']);
        static::assertStringContainsString('ACME-123', $response['svg']);
        static::assertStringContainsString('3', $response['svg']);

        static::assertIsString($response['nestedSvg']);
        static::assertStringContainsString('3', $response['nestedSvg']);
    }

    public function testGaranLabelIsNullWhenGuaranteeConfirmedIsFalse(): void
    {
        $product = (new ProductBuilder($this->ids, 'garan-product-unconfirmed'))
            ->price(10)
            ->visibility($this->ids->get('sales-channel'))
            ->manufacturer('acme')
            ->build();
        $product['manufacturerNumber'] = 'ACME-123';
        $product['guaranteeMonths'] = 36;
        $product['guaranteeConfirmed'] = false;

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        $this->browser->request('GET', '/store-api/product/' . $this->ids->get('garan-product-unconfirmed') . '/garan-label');

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($response['svg']);
        static::assertNull($response['nestedSvg']);
    }

    public function testGaranLabelIsNullWhenManufacturerNumberIsMissingEvenWithProductNumber(): void
    {
        $product = (new ProductBuilder($this->ids, 'garan-product-no-manufacturer-number'))
            ->price(10)
            ->visibility($this->ids->get('sales-channel'))
            ->manufacturer('acme')
            ->build();
        $product['guaranteeMonths'] = 36;
        $product['guaranteeConfirmed'] = true;

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        $this->browser->request('GET', '/store-api/product/' . $this->ids->get('garan-product-no-manufacturer-number') . '/garan-label');

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($response['svg']);
        static::assertNull($response['nestedSvg']);
    }

    public function testGaranLabelIsNullWhenGuaranteeMonthsIsMissing(): void
    {
        $product = (new ProductBuilder($this->ids, 'garan-product-no-guarantee'))
            ->price(10)
            ->visibility($this->ids->get('sales-channel'))
            ->manufacturer('acme')
            ->build();

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        $this->browser->request('GET', '/store-api/product/' . $this->ids->get('garan-product-no-guarantee') . '/garan-label');

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($response['svg']);
        static::assertNull($response['nestedSvg']);
    }

    public function testGaranLabelRouteReturnsNotFoundForUnknownProduct(): void
    {
        $this->browser->request('GET', '/store-api/product/' . $this->ids->create('unknown-product') . '/garan-label');

        static::assertSame(404, $this->browser->getResponse()->getStatusCode());
    }
}
