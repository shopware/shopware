<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1620820321AddDefaultDomainForHeadlessSaleschannel;

class Migration1620820321AddDefaultDomainForHeadlessSaleschannelTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->removeAddedDefaultDomains();
    }

    public function testItAddsDefaultDomainToHeadlessSalesChannel(): void
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM `sales_channel_domain` WHERE `sales_channel_id` = :salesChannelId');
        $statement->bindValue('salesChannelId', Uuid::fromHexToBytes(Defaults::SALES_CHANNEL));

        static::assertEquals(0, $statement->fetchOne());

        (new Migration1620820321AddDefaultDomainForHeadlessSaleschannel())->update($this->connection);

        $statement->execute();
        static::assertEquals(1, $statement->fetchOne());
    }

    public function testItAddsDefaultDomainToMultipleApiSalesChannel(): void
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM `sales_channel_domain` WHERE `sales_channel_id` = :salesChannelId');

        $firstApiSalesChannelId = $this->addSalesChannel(Defaults::SALES_CHANNEL_TYPE_API);
        $secondApiSalesChannelId = $this->addSalesChannel(Defaults::SALES_CHANNEL_TYPE_API);
        $firstStorefrontSalesChannelId = $this->addSalesChannel(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        (new Migration1620820321AddDefaultDomainForHeadlessSaleschannel())->update($this->connection);

        $statement->execute(['salesChannelId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL)]);
        static::assertEquals(1, $statement->fetchOne());

        $statement->execute(['salesChannelId' => Uuid::fromHexToBytes($firstApiSalesChannelId)]);
        static::assertEquals(1, $statement->fetchOne());

        $statement->execute(['salesChannelId' => Uuid::fromHexToBytes($secondApiSalesChannelId)]);
        static::assertEquals(1, $statement->fetchOne());

        $statement->execute(['salesChannelId' => Uuid::fromHexToBytes($firstStorefrontSalesChannelId)]);
        static::assertEquals(0, $statement->fetchOne());
    }

    public function testItDoesNotBreakIfNoHeadlessSalesChannelIsPresent(): void
    {
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $salesChannelRepository->delete([['id' => Defaults::SALES_CHANNEL]], Context::createDefaultContext());

        (new Migration1620820321AddDefaultDomainForHeadlessSaleschannel())->update($this->connection);
    }

    private function removeAddedDefaultDomains(): void
    {
        $this->connection->executeStatement('
            DELETE FROM `sales_channel_domain`
            WHERE `url` = "default.headless0"
        ');
    }

    private function addSalesChannel(string $salesChannelType): string
    {
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $id = Uuid::randomHex();

        $paymentMethod = $this->getAvailablePaymentMethod();

        $salesChannelRepository->create([
            [
                'id' => $id,
                'typeId' => $salesChannelType,
                'name' => 'API Test case sales channel',
                'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
                'paymentMethodId' => $paymentMethod->getId(),
                'paymentMethods' => [['id' => $paymentMethod->getId()]],
                'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
                'navigationCategoryId' => $this->getValidCategoryId(),
                'countryId' => $this->getValidCountryId(null),
                'currencies' => [['id' => Defaults::CURRENCY]],
                'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
                'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'countries' => [['id' => $this->getValidCountryId(null)]],
            ],
        ], Context::createDefaultContext());

        return $id;
    }
}
