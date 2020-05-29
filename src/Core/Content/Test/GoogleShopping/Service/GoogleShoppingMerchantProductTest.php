<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount\GoogleShoppingMerchantAccountEntity;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingMerchantProduct;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use function Flag\skipTestNext6050;

class GoogleShoppingMerchantProductTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var GoogleShoppingMerchantProduct
     */
    private $merchantProductService;

    protected function setUp(): void
    {
        skipTestNext6050($this);
        $this->getMockGoogleClient();
        $this->context = Context::createDefaultContext();
        $this->merchantProductService = $this->getContainer()->get(GoogleShoppingMerchantProduct::class);
    }

    public function testListWithGoogleStatus(): void
    {
        $storeFrontSaleChannelId = $this->createStorefrontSalesChannel();

        $criteria = new Criteria([$storeFrontSaleChannelId]);
        $criteria->addAssociation('language.locale');
        $criteria->addAssociation('country');

        $storeFrontSaleChannel = $this->getContainer()->get('sales_channel.repository')->search($criteria, $this->context)->get($storeFrontSaleChannelId);

        $productNumbers = ['product1', 'product2', 'product3'];

        $merchant = new GoogleShoppingMerchantAccountEntity();
        $merchant->setMerchantId('merchant1');
        $merchant->setDatafeedId('datafeed1');

        $listProducts = $this->merchantProductService->listWithGoogleStatus($merchant, $storeFrontSaleChannel, $productNumbers);

        static::assertNotEmpty($listProducts);
        static::assertCount(count($productNumbers), $listProducts);
        static::assertEquals($productNumbers, array_keys($listProducts));
    }
}
