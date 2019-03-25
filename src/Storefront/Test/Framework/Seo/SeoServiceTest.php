<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\SeoService;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;

class SeoServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $templateRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $seoUrlRepository;

    /**
     * @var SeoService
     */
    private $seoService;

    public function setUp(): void
    {
        $this->templateRepository = $this->getContainer()->get('seo_url_template.repository');
        $this->seoUrlRepository = $this->getContainer()->get('seo_url.repository');
        $this->seoService = $this->getContainer()->get(SeoService::class);

        $connection = $this->getContainer()->get(Connection::class);
        $connection->exec('DELETE FROM `sales_channel`');
    }

    public function testUpdateSeoUrls(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::uuid4()->getHex(), 'test');

        $fk = Uuid::uuid4()->getHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $this->seoService->updateSeoUrls($salesChannel->getId(), 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $this->seoService->updateSeoUrls($salesChannel->getId(), 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path-2',
            ],
        ];
        $this->seoService->updateSeoUrls($salesChannel->getId(), 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);
    }

    public function testDuplicatesSameSalesChannel(): void
    {
        $salesChannel = $this->createSalesChannel(Uuid::uuid4()->getHex(), 'test');

        $fk1 = Uuid::uuid4()->getHex();
        $fk2 = Uuid::uuid4()->getHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk1,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
            [
                'foreignKey' => $fk2,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoService->updateSeoUrls($salesChannel->getId(), 'r', $fks, $seoUrlUpdates);

        /** @var SeoUrlCollection $result */
        $result = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();

        /** @var SeoUrlEntity $valid */
        $valid = $result->filterByProperty('isValid', true)->first();
        /** @var SeoUrlEntity $invalid */
        $invalid = $result->filterByProperty('isValid', false)->first();

        static::assertNotNull($valid);
        static::assertNotNull($invalid);

        static::assertEquals($fk1, $valid->getForeignKey());
        static::assertEquals($fk2, $invalid->getForeignKey());
    }

    public function testSameSeoPathInfoDifferentSalesChannels(): void
    {
        $salesChannelA = $this->createSalesChannel(Uuid::uuid4()->getHex(), 'test a');
        $salesChannelB = $this->createSalesChannel(Uuid::uuid4()->getHex(), 'test b');

        $fk = Uuid::uuid4()->getHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoService->updateSeoUrls($salesChannelA->getId(), 'r', $fks, $seoUrlUpdates);
        $this->seoService->updateSeoUrls($salesChannelB->getId(), 'r', $fks, $seoUrlUpdates);

        /** @var SeoUrlCollection $result */
        $result = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();

        $validSeoUrls = $result->filterByProperty('isValid', true);
        static::assertCount(2, $validSeoUrls);

        $invalidSeoUrls = $result->filterByProperty('isValid', false);
        static::assertCount(0, $invalidSeoUrls);
    }

    private function createSalesChannel(string $id, string $name, string $defaultLanguageId = Defaults::LANGUAGE_SYSTEM, array $languageIds = []): SalesChannelEntity
    {
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('sales_channel.repository');
        $languageIds[] = $defaultLanguageId;
        $languageIds = array_unique($languageIds);

        $languages = [];
        foreach ($languageIds as $langId) {
            $languages[] = ['id' => $langId];
        }

        $repo->upsert([[
            'id' => $id,
            'name' => $name,
            'typeId' => Defaults::SALES_CHANNEL_STOREFRONT,
            'accessKey' => Uuid::uuid4()->getHex(),
            'secretAccessKey' => 'foobar',
            'languageId' => $defaultLanguageId,
            'snippetSetId' => Defaults::SNIPPET_BASE_SET_EN,
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_DEBIT,
            'shippingMethodId' => Defaults::SHIPPING_METHOD,
            'countryId' => Defaults::COUNTRY,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => $languages,
            'paymentMethods' => [['id' => Defaults::PAYMENT_METHOD_DEBIT]],
            'shippingMethods' => [['id' => Defaults::SHIPPING_METHOD]],
            'countries' => [['id' => Defaults::COUNTRY]],
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ]], Context::createDefaultContext());

        return $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();
    }
}
