<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelStruct;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SalesChannelRepositoryTest extends KernelTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $salesChannelRepository;

    public function setUp()
    {
        self::bootKernel();
        $this->salesChannelRepository = self::$container->get('sales_channel.repository');
        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testCreateSalesChannelTest()
    {
        $salesChannelId = Uuid::uuid4()->getHex();
        $accessKey = AccessKeyHelper::generateAccessKey('sales-channel');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $name = 'Repository test';
        $cover = 'http://example.org/icon1.jpg';
        $icon = 'sw-icon';
        $screenshots = [
            'http://example.org/image.jpg',
            'http://example.org/image2.jpg',
            'http://example.org/image3.jpg',
        ];
        $typeName = 'test type';
        $manufacturer = 'shopware';
        $description = 'my description';
        $descriptionLong = 'an even longer description';

        $this->salesChannelRepository->upsert([[
            'id' => $salesChannelId,
            'name' => $name,
            'type' => [
                'cover' => $cover,
                'icon' => $icon,
                'screenshots' => $screenshots,
                'name' => $typeName,
                'manufacturer' => $manufacturer,
                'description' => $description,
                'descriptionLong' => $descriptionLong,
            ],
            'accessKey' => $accessKey,
            'secretAccessKey' => $secretKey,
            'languageId' => Defaults::LANGUAGE,
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_DEBIT,
            'shippingMethodId' => Defaults::SHIPPING_METHOD,
            'countryId' => Defaults::COUNTRY,
            'catalogIds' => [Defaults::CATALOG],
            'currencyIds' => [Defaults::CURRENCY],
            'languageIds' => [Defaults::LANGUAGE],
        ]], $context);

        /** @var SalesChannelStruct $salesChannel */
        $salesChannel = $this->salesChannelRepository->read(new ReadCriteria([$salesChannelId]), $context)->get($salesChannelId);

        self::assertEquals($name, $salesChannel->getName());
        self::assertEquals($accessKey, $salesChannel->getAccessKey());
        self::assertTrue(password_verify($secretKey, $salesChannel->getSecretAccessKey()));

        self::assertEquals($cover, $salesChannel->getType()->getCover());
        self::assertEquals($icon, $salesChannel->getType()->getIcon());
        self::assertEquals($screenshots, $salesChannel->getType()->getScreenshots());
        self::assertEquals($typeName, $salesChannel->getType()->getName());
        self::assertEquals($manufacturer, $salesChannel->getType()->getManufacturer());
        self::assertEquals($description, $salesChannel->getType()->getDescription());
        self::assertEquals($descriptionLong, $salesChannel->getType()->getDescriptionLong());
    }
}
