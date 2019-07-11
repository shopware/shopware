<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class SalesChannelRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $countryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $shippingMethodRepository;

    protected function setUp(): void
    {
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $this->currencyRepository = $this->getContainer()->get('currency.repository');
        $this->languageRepository = $this->getContainer()->get('language.repository');
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $this->countryRepository = $this->getContainer()->get('country.repository');
        $this->shippingMethodRepository = $this->getContainer()->get('shipping_method.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testCreateSalesChannelTest(): void
    {
        $salesChannelId = Uuid::randomHex();
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
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $this->getValidCountryId(),
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ]], $context);

        $criteria1 = new Criteria([$salesChannelId]);
        $criteria1->addAssociation('type');

        $salesChannel = $this->salesChannelRepository->search($criteria1, $context)->get($salesChannelId);

        static::assertEquals($name, $salesChannel->getName());
        static::assertEquals($accessKey, $salesChannel->getAccessKey());

        static::assertEquals($cover, $salesChannel->getType()->getCoverUrl());
        static::assertEquals($icon, $salesChannel->getType()->getIconName());
        static::assertEquals($screenshots, $salesChannel->getType()->getScreenshotUrls());
        static::assertEquals($typeName, $salesChannel->getType()->getName());
        static::assertEquals($manufacturer, $salesChannel->getType()->getManufacturer());
        static::assertEquals($description, $salesChannel->getType()->getDescription());
        static::assertEquals($descriptionLong, $salesChannel->getType()->getDescriptionLong());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('currency.salesChannels.id', $salesChannelId));
        $currency = $this->currencyRepository->search($criteria, $context);
        static::assertEquals(1, $currency->count());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.salesChannels.id', $salesChannelId));
        $language = $this->languageRepository->search($criteria, $context);
        static::assertEquals(1, $language->count());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('payment_method.salesChannels.id', $salesChannelId));
        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context);
        static::assertEquals(1, $paymentMethod->count());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('country.salesChannels.id', $salesChannelId));
        $country = $this->countryRepository->search($criteria, $context);
        static::assertEquals(1, $country->count());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shipping_method.salesChannels.id', $salesChannelId));
        $shippingMethod = $this->shippingMethodRepository->search($criteria, $context);
        static::assertEquals(1, $shippingMethod->count());
    }
}
