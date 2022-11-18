<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class EntryPointValidatorTest extends TestCase
{
    use KernelTestBehaviour;
    use BasicTestDataBehaviour;

    /**
     * @var EntityRepository
     */
    private $categoryRepository;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->getContainer()->get(sprintf('%s.repository', CategoryDefinition::ENTITY_NAME));
        $this->salesChannelRepository = $this->getContainer()->get(sprintf('%s.repository', SalesChannelDefinition::ENTITY_NAME));
    }

    public function testChangeNavigationFail(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->getValidCategoryId();
        $this->salesChannelRepository->update([
            [
                'id' => TestDefaults::SALES_CHANNEL,
                'navigationCategoryId' => $categoryId,
            ],
        ], $context);

        $this->expectException(WriteException::class);
        $this->categoryRepository->update([
            [
                'id' => $categoryId,
                'type' => CategoryDefinition::TYPE_LINK,
            ],
        ], $context);
    }

    public function testChangeServiceFail(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->getValidCategoryId();

        $this->expectException(WriteException::class);
        $this->salesChannelRepository->update([
            [
                'id' => TestDefaults::SALES_CHANNEL,
                'serviceCategory' => [
                    'id' => $categoryId,
                    'type' => CategoryDefinition::TYPE_LINK,
                ],
            ],
        ], $context);
    }

    public function testChangeFooterValid(): void
    {
        $context = Context::createDefaultContext();
        $categoryId = $this->getValidCategoryId();
        $this->salesChannelRepository->update([
            [
                'id' => TestDefaults::SALES_CHANNEL,
                'footerCategory' => [
                    'id' => $categoryId,
                    'type' => CategoryDefinition::TYPE_PAGE,
                ],
            ],
        ], $context);

        /** @var CategoryEntity|null $category */
        $category = $this->categoryRepository->search(new Criteria([$categoryId]), $context)->first();
        static::assertNotNull($category);
        static::assertSame(CategoryDefinition::TYPE_PAGE, $category->getType());
    }
}
