<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelStruct;

class SalesChannelRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var RepositoryInterface
     */
    private $catalogRepository;
    /**
     * @var RepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var RepositoryInterface
     */
    private $languageRepository;

    /**
     * @var RepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var RepositoryInterface
     */
    private $countryRepository;

    /**
     * @var RepositoryInterface
     */
    private $shippingMethodRepository;

    public function setUp()
    {
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $this->catalogRepository = $this->getContainer()->get('catalog.repository');
        $this->currencyRepository = $this->getContainer()->get('currency.repository');
        $this->languageRepository = $this->getContainer()->get('language.repository');
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $this->countryRepository = $this->getContainer()->get('country.repository');
        $this->shippingMethodRepository = $this->getContainer()->get('shipping_method.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testCreateSalesChannelTest(): void
    {
        $salesChannelId = Uuid::uuid4()->getHex();
        $accessKey = AccessKeyHelper::generateAccessKey('sales-channel');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();
        $context = Context::createDefaultContext();

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
                'coverUrl' => $cover,
                'iconName' => $icon,
                'screenshotUrls' => $screenshots,
                'name' => $typeName,
                'manufacturer' => $manufacturer,
                'description' => $description,
                'descriptionLong' => $descriptionLong,
            ],
            'accessKey' => $accessKey,
            'secretAccessKey' => $secretKey,
            'languageId' => Defaults::LANGUAGE_EN,
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_DEBIT,
            'shippingMethodId' => Defaults::SHIPPING_METHOD,
            'countryId' => Defaults::COUNTRY,
            'catalogs' => [['id' => Defaults::CATALOG]],
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_EN]],
            'paymentMethods' => [['id' => Defaults::PAYMENT_METHOD_DEBIT]],
            'shippingMethods' => [['id' => Defaults::SHIPPING_METHOD]],
            'countries' => [['id' => Defaults::COUNTRY]],
        ]], $context);

        /** @var SalesChannelStruct $salesChannel */
        $salesChannel = $this->salesChannelRepository->read(new ReadCriteria([$salesChannelId]), $context)->get($salesChannelId);

        self::assertEquals($name, $salesChannel->getName());
        self::assertEquals($accessKey, $salesChannel->getAccessKey());

        self::assertEquals($cover, $salesChannel->getType()->getCoverUrl());
        self::assertEquals($icon, $salesChannel->getType()->getIconName());
        self::assertEquals($screenshots, $salesChannel->getType()->getScreenshotUrls());
        self::assertEquals($typeName, $salesChannel->getType()->getName());
        self::assertEquals($manufacturer, $salesChannel->getType()->getManufacturer());
        self::assertEquals($description, $salesChannel->getType()->getDescription());
        self::assertEquals($descriptionLong, $salesChannel->getType()->getDescriptionLong());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('catalog.salesChannels.id', $salesChannelId));
        $catalog = $this->catalogRepository->search($criteria, $context);
        self::assertEquals(1, $catalog->count());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('currency.salesChannels.id', $salesChannelId));
        $currency = $this->currencyRepository->search($criteria, $context);
        self::assertEquals(1, $currency->count());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.salesChannels.id', $salesChannelId));
        $language = $this->languageRepository->search($criteria, $context);
        self::assertEquals(1, $language->count());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('payment_method.salesChannels.id', $salesChannelId));
        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context);
        self::assertEquals(1, $paymentMethod->count());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('country.salesChannels.id', $salesChannelId));
        $country = $this->countryRepository->search($criteria, $context);
        self::assertEquals(1, $country->count());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shipping_method.salesChannels.id', $salesChannelId));
        $shippingMethod = $this->shippingMethodRepository->search($criteria, $context);
        self::assertEquals(1, $shippingMethod->count());
    }
}
