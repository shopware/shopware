<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\GoogleShopping\Subscriber\DatafeedProductExportSubscriber;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingDatafeedIntegration;
use Shopware\Core\Content\Test\GoogleShopping\GoogleShoppingIntegration;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use function Flag\skipTestNext6050;

class DatafeedProductExportSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GoogleShoppingIntegration;
    use GoogleShoppingDatafeedIntegration;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        skipTestNext6050($this);
        $this->context = Context::createDefaultContext();
        $this->getMockGoogleClient();
    }

    public function testGetSubscribedEvents(): void
    {
        $events = DatafeedProductExportSubscriber::getSubscribedEvents();

        static::assertCount(1, $events);
        static::assertEquals('writeDatafeed', $events['product_export.written']);
    }

    public function testWriteDatafeed(): void
    {
        $merchantId = Uuid::randomHex();
        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $googleAccounts = $this->createGoogleShoppingAccount(Uuid::randomHex(), $salesChannelId);

        $this->connectGoogleShoppingMerchantAccount($googleAccounts['googleAccount']['id'], $merchantId);

        $this->createProductExportEntity($salesChannelId);

        $merchantAccount = $this->getMerchantAccountEntity($googleAccounts);

        static::assertEquals($merchantId, $merchantAccount->getMerchantId());
    }
}
