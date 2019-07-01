<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlGenerator;
use Shopware\Storefront\Framework\Seo\SeoUrlPersister;

class SeoUrlPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    /**
     * @var EntityRepositoryInterface
     */
    private $templateRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $seoUrlRepository;

    /**
     * @var SeoUrlGenerator
     */
    private $seoUrlPersister;

    public function setUp(): void
    {
        $this->templateRepository = $this->getContainer()->get('seo_url_template.repository');
        $this->seoUrlRepository = $this->getContainer()->get('seo_url.repository');
        $this->seoUrlPersister = $this->getContainer()->get(SeoUrlPersister::class);

        $connection = $this->getContainer()->get(Connection::class);
        $connection->exec('DELETE FROM `sales_channel`');
        $connection->exec('DELETE FROM `seo_url`');
    }

    public function testUpdateSeoUrlsDefault(): void
    {
        $context = Context::createDefaultContext();

        $fk = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path-2',
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(2, $seoUrls);

        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        static::assertCount(1, $canonicalUrls);
        /** @var SeoUrlEntity $first */
        $first = $canonicalUrls->first();
        static::assertEquals('fancy-path-2', $first->getSeoPathInfo());

        $obsoletedSeoUrls = $seoUrls->filterByProperty('isCanonical', false);

        static::assertCount(1, $obsoletedSeoUrls);
        /** @var SeoUrlEntity $first */
        $first = $obsoletedSeoUrls->first();
        static::assertEquals('fancy-path', $first->getSeoPathInfo());
    }

    public function testDuplicatesSameSalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $fk1 = Uuid::randomHex();
        $fk2 = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'salesChannelId' => $salesChannelId,
                'foreignKey' => $fk1,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
            [
                'salesChannelId' => $salesChannelId,
                'foreignKey' => $fk2,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls(Context::createDefaultContext(), 'r', $fks, $seoUrlUpdates);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('foreignKey', [$fk1, $fk2]));
        /** @var SeoUrlCollection $result */
        $result = $this->seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();

        /** @var SeoUrlEntity $valid */
        $valid = $result->filterByProperty('isValid', true)->first();
        /** @var SeoUrlEntity $invalid */
        $invalid = $result->filterByProperty('isValid', false)->first();

        static::assertNotNull($valid);
        static::assertNotNull($invalid);

        static::assertEquals($fk1, $valid->getForeignKey());
        static::assertEquals($fk2, $invalid->getForeignKey());
    }

    public function testSameSeoPathDifferentLanguage(): void
    {
        $defaultContext = Context::createDefaultContext();
        $deContext = new Context($defaultContext->getSource(), [], $defaultContext->getCurrencyId(), [$this->getDeDeLanguageId()]);

        $fk = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($defaultContext, 'r', $fks, $seoUrlUpdates);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($deContext, 'r', $fks, $seoUrlUpdates);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('routeName', 'r'));
        /** @var SeoUrlCollection $result */
        $result = $this->seoUrlRepository->search($criteria, $defaultContext)->getEntities();
        $validSeoUrls = $result->filterByProperty('isValid', true);
        static::assertCount(2, $validSeoUrls);

        $invalidSeoUrls = $result->filterByProperty('isValid', false);
        static::assertCount(0, $invalidSeoUrls);
    }

    public function testSameSeoPathInfoDifferentSalesChannels(): void
    {
        $context = Context::createDefaultContext();

        $salesChannelAId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelAId, 'test a');

        $salesChannelBId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelBId, 'test b');

        $fk = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'salesChannelId' => $salesChannelAId,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'salesChannelId' => $salesChannelBId,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('routeName', 'r'));
        /** @var SeoUrlCollection $result */
        $result = $this->seoUrlRepository->search($criteria, $context)->getEntities();

        $validSeoUrls = $result->filterByProperty('isValid', true);
        static::assertCount(3, $validSeoUrls);

        $invalidSeoUrls = $result->filterByProperty('isValid', false);
        static::assertCount(0, $invalidSeoUrls);
    }

    public function testUpdateDefaultIsModified(): void
    {
        $context = Context::createDefaultContext();

        $fk = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'default',
                'isModified' => false,
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-override',
                'isModified' => true,
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(2, $seoUrls);
        $canon = $seoUrls->filterByProperty('isCanonical', true)->first();
        static::assertNotNull($canon);

        static::assertTrue($canon->getIsModified());
        static::assertEquals('fancy-override', $canon->getSeoPathInfo());

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'no-effect',
            ],
        ];

        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(2, $seoUrls);
        $canon = $seoUrls->filterByProperty('isCanonical', true)->first();
        static::assertNotNull($canon);

        static::assertTrue($canon->getIsModified());
        static::assertNotEquals('no-effect', $canon->getSeoPathInfo());
    }
}
