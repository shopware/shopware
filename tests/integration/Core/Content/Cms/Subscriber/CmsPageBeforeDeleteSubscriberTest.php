<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Cms\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsException;
use Shopware\Core\Content\Cms\Subscriber\CmsPageDefaultChangeSubscriber;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 *
 * @package content
 */
#[Package('buyers-experience')]
#[CoversClass(CmsPageDefaultChangeSubscriber::class)]
class CmsPageBeforeDeleteSubscriberTest extends TestCase
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

    public function testDeleteCmsPageDoesNotThrow(): void
    {
        $defaultCmsPageId = Uuid::randomHex();
        $this->createCmsPage($defaultCmsPageId);

        // create cms page to delete
        $cmsPageId = Uuid::randomHex();
        $this->createCmsPage($cmsPageId);

        $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $defaultCmsPageId, null);
        $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $defaultCmsPageId, TestDefaults::SALES_CHANNEL);

        $id = $this->cmsPageRepository->searchIds(new Criteria([$cmsPageId]), Context::createDefaultContext())->firstId();
        static::assertEquals($cmsPageId, $id);

        // delete cms page which is not default
        $this->cmsPageRepository->delete([['id' => $cmsPageId]], Context::createDefaultContext());

        $id = $this->cmsPageRepository->searchIds(new Criteria([$cmsPageId]), Context::createDefaultContext())->firstId();
        static::assertNull($id);
    }

    public function testDeleteOverallDefaultCmsPageThrow(): void
    {
        $cmsPageId = Uuid::randomHex();
        $exceptionWasThrown = false;
        $this->createCmsPage($cmsPageId);

        // set cms page id as overall default
        $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $cmsPageId, null);

        // at this point we do not differentiate whether this is an overall default or not
        try {
            $this->cmsPageRepository->delete([['id' => $cmsPageId]], Context::createDefaultContext());
        } catch (CmsException $exception) {
            static::assertEquals(CmsException::DELETION_OF_DEFAULT_CODE, $exception->getErrorCode());
            $exceptionWasThrown = true;
        } finally {
            if (!$exceptionWasThrown) {
                static::fail('Expected exception with error code ' . CmsException::DELETION_OF_DEFAULT_CODE . ' to be thrown.');
            }
        }
    }

    public function testDeleteDefaultCmsPageThrow(): void
    {
        $cmsPageId = Uuid::randomHex();
        $exceptionWasThrown = false;
        $this->createCmsPage($cmsPageId);

        // set cms page id as sales channel specific default
        $this->systemConfigService->set(ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT, $cmsPageId, TestDefaults::SALES_CHANNEL);

        try {
            $this->cmsPageRepository->delete([['id' => $cmsPageId]], Context::createDefaultContext());
        } catch (CmsException $exception) {
            static::assertEquals(CmsException::DELETION_OF_DEFAULT_CODE, $exception->getErrorCode());
            $exceptionWasThrown = true;
        } finally {
            if (!$exceptionWasThrown) {
                static::fail('Expected exception with error code ' . CmsException::DELETION_OF_DEFAULT_CODE . ' to be thrown.');
            }
        }
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
