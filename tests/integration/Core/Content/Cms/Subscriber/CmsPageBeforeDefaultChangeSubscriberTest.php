<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Cms\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsException;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\Subscriber\CmsPageDefaultChangeSubscriber;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CmsPageDefaultChangeSubscriber::class)]
class CmsPageBeforeDefaultChangeSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $cmsPageRepository;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cmsPageRepository = $this->getContainer()->get('cms_page.repository');
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
    }

    #[DataProvider('validDefaultCmsPageDataProvider')]
    public function testSetDefaultDoesNotThrow(string $validCmsPageId, ?string $salesChannelId): void
    {
        $this->createCmsPage($validCmsPageId);

        $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $validCmsPageId, $salesChannelId);

        // assert no exception was thrown
        static::assertTrue(true);
    }

    #[DataProvider('invalidDefaultCmsPageDataProvider')]
    public function testSetInvalidDefaultThrow(string $invalidCmsPageId, string $expectedException, ?string $salesChannelId): void
    {
        /** @var class-string<\Throwable> $expectedException */
        static::expectException($expectedException);
        $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $invalidCmsPageId, $salesChannelId);
    }

    public function testDeleteSalesChannelDefaultDoesNotThrow(): void
    {
        $cmsPage = Uuid::randomHex();
        $this->createCmsPage($cmsPage);

        // set sales channel specific default
        $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $cmsPage, TestDefaults::SALES_CHANNEL);

        // expect to be able to delete the default
        $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, null, TestDefaults::SALES_CHANNEL);

        // assert no expection was thrown
        static::assertTrue(true);
    }

    public function testDeleteOverallDefaultThrow(): void
    {
        $cmsPage = Uuid::randomHex();
        $exceptionWasThrown = false;
        $this->createCmsPage($cmsPage);

        // set overall default
        $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $cmsPage, null);

        try {
            $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, null, null);
        } catch (CmsException $exception) {
            static::assertEquals(CmsException::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE, $exception->getErrorCode());
            $exceptionWasThrown = true;
        } finally {
            if (!$exceptionWasThrown) {
                static::fail('Expected exception with error code ' . CmsException::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE . ' to be thrown.');
            }
        }
    }

    public static function validDefaultCmsPageDataProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield [
            $ids->get('validCmsPageId'),
            null,
        ];

        yield [
            $ids->get('validCmsPageId'),
            TestDefaults::SALES_CHANNEL,
        ];
    }

    public static function invalidDefaultCmsPageDataProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield [
            $ids->get('invalidCmsPageId'),
            PageNotFoundException::class,
            null,
        ];

        yield [
            $ids->get('invalidCmsPageId'),
            PageNotFoundException::class,
            TestDefaults::SALES_CHANNEL,
        ];
    }

    private function createCmsPage(string $cmsPageId): void
    {
        $cmsPage = [
            'id' => $cmsPageId,
            'name' => 'test page',
            'type' => 'product_detail',
        ];

        $this->cmsPageRepository->create([$cmsPage], Context::createDefaultContext());
    }
}
