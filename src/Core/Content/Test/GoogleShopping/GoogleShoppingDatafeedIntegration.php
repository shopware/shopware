<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

trait GoogleShoppingDatafeedIntegration
{
    public function createProductExportEntity(string $salesChannelId): string
    {
        $this->createProductStreamDataFeed($salesChannelId);

        $id = Uuid::randomHex();
        $this->getContainer()->get('product_export.repository')->upsert([
            [
                'id' => $id,
                'fileName' => 'Testexport.csv',
                'accessKey' => Uuid::randomHex(),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'fileFormat' => ProductExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'headerTemplate' => 'name,stock',
                'bodyTemplate' => '{{ product.name }},{{ product.stock }}',
                'productStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'storefrontSalesChannelId' => $salesChannelId,
                'salesChannelId' => $salesChannelId,
                'salesChannelDomainId' => $this->getSalesChannelDomain()->getId(),
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        return $id;
    }

    public function createProductStreamDataFeed(string $salesChannelId): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $randomProductIds = implode('|', array_slice(array_column($this->createProducts($salesChannelId), 'id'), 0, 2));

        $connection->exec("
            INSERT INTO `product_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('137B079935714281BA80B40F83F8D7EB'), '[{\"type\": \"multi\", \"queries\": [{\"type\": \"multi\", \"queries\": [{\"type\": \"equalsAny\", \"field\": \"product.id\", \"value\": \"{$randomProductIds}\"}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"product.width\", \"parameters\": {\"gte\": 221, \"lte\": 932}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"product.width\", \"parameters\": {\"lte\": 245}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"equals\", \"field\": \"product.manufacturer.id\", \"value\": \"02f6b9aa385d4f40aaf573661b2cf919\"}, {\"type\": \"range\", \"field\": \"product.height\", \"parameters\": {\"gte\": 182}}], \"operator\": \"AND\"}], \"operator\": \"OR\"}]', 0, '2019-08-16 08:43:57.488', NULL);
        ");

        $connection->exec("
            INSERT INTO `product_stream_filter` (`id`, `product_stream_id`, `parent_id`, `type`, `field`, `operator`, `value`, `parameters`, `position`, `custom_fields`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), UNHEX('137B079935714281BA80B40F83F8D7EB'), NULL, 'multi', NULL, 'OR', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.469', NULL),
                (UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 1, NULL, '2019-08-16 08:43:57.478', NULL),
                (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, '{$randomProductIds}', NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL);
    ");
    }

    public function getSalesChannelDomain(): SalesChannelDomainEntity
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('sales_channel_domain.repository');

        return $repository->search(new Criteria(), $this->context)->first();
    }

    public function getSalesChannel(string $salesChannelId): SalesChannelEntity
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('googleShoppingAccount.googleShoppingMerchantAccount');
        $criteria->addAssociation('productExports.currency');
        $criteria->addAssociation('productExports.salesChannelDomain');
        $criteria->addAssociation('productExports.storefrontSalesChannel.shippingMethod');
        $criteria->addAssociation('productExports.storefrontSalesChannel.country');
        $criteria->addAssociation('productExports.storefrontSalesChannel.language');
        $criteria->addAssociation('productExports.storefrontSalesChannel.language.locale');

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('sales_channel.repository');

        return $repository->search($criteria, $this->context)->first();
    }
}
