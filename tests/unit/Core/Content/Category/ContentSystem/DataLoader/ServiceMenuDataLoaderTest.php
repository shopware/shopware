<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\ServiceMenuDataLoader;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\ServiceMenuLoaderConfig;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ServiceMenuDataLoader::class)]
class ServiceMenuDataLoaderTest extends TestCase
{
    private NavigationLoaderInterface&MockObject $navigationLoader;

    private ServiceMenuDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $this->dataLoader = new ServiceMenuDataLoader($this->navigationLoader, new NavigationAliasResolver());
    }

    #[TestDox('returns service_menu source type identifier')]
    public function testGetRequirementTypeReturnsServiceMenuString(): void
    {
        static::assertSame('service_menu', ServiceMenuDataLoader::getRequirementType());
    }

    #[TestDox('resolves provided data type from annotation')]
    public function testGetProvidedDataResolvesExpectedType(): void
    {
        $descriptor = ServiceMenuDataLoader::getProvidedData();

        static::assertSame(CategoryCollection::class, $descriptor->className);
        static::assertSame([], $descriptor->genericParameters);
    }

    #[TestDox('loads service menu categories flattened from navigation tree')]
    public function testLoadReturnsFlattenedCategoryCollection(): void
    {
        $serviceCategoryId = Uuid::randomHex();
        $categoryA = new CategoryEntity();
        $categoryA->setId(Uuid::randomHex());
        $categoryA->setUniqueIdentifier($categoryA->getId());
        $categoryB = new CategoryEntity();
        $categoryB->setId(Uuid::randomHex());
        $categoryB->setUniqueIdentifier($categoryB->getId());

        $tree = new Tree(null, [
            new TreeItem($categoryA, []),
            new TreeItem($categoryB, []),
        ]);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new ServiceMenuLoaderConfig();
        $requirement = new DataRequirement('serviceMenu', 'service_menu', $config);
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setServiceCategoryId($serviceCategoryId);

        $this->navigationLoader
            ->method('load')
            ->with($serviceCategoryId, $context, $serviceCategoryId, 1)
            ->willReturn($tree);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertInstanceOf(CategoryCollection::class, $result->data);
        static::assertCount(2, $result->data);
        static::assertSame($categoryA, $result->data->first());
        static::assertSame($categoryB, $result->data->last());
    }

    #[TestDox('uses explicit rootId from config instead of service-navigation alias')]
    public function testLoadUsesExplicitRootIdFromConfig(): void
    {
        $rootId = Uuid::randomHex();
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setUniqueIdentifier($category->getId());

        $tree = new Tree(null, [new TreeItem($category, [])]);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new ServiceMenuLoaderConfig(rootId: $rootId);
        $requirement = new DataRequirement('serviceMenu', 'service_menu', $config);
        $context = Generator::generateSalesChannelContext();

        $this->navigationLoader
            ->method('load')
            ->with($rootId, $context, $rootId, 1)
            ->willReturn($tree);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertInstanceOf(CategoryCollection::class, $result->data);
        static::assertCount(1, $result->data);
    }

    #[TestDox('returns empty cached category collection when tree has no items')]
    public function testLoadReturnsEmptyCachedCollectionWhenTreeHasNoItems(): void
    {
        $serviceCategoryId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new ServiceMenuLoaderConfig();
        $requirement = new DataRequirement('serviceMenu', 'service_menu', $config);
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setServiceCategoryId($serviceCategoryId);

        $this->navigationLoader->method('load')->willReturn($tree);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertInstanceOf(CategoryCollection::class, $result->data);
        static::assertCount(0, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns empty CategoryCollection when service category is not configured')]
    public function testLoadReturnsEmptyCollectionWhenServiceCategoryNotConfigured(): void
    {
        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new ServiceMenuLoaderConfig();
        $requirement = new DataRequirement('serviceMenu', 'service_menu', $config);
        $context = Generator::generateSalesChannelContext();

        $this->navigationLoader->expects($this->never())->method('load');

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertInstanceOf(CategoryCollection::class, $result->data);
        static::assertCount(0, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when config is not a ServiceMenuLoaderConfig instance')]
    public function testLoadReturnNotFoundWhenConfigIsNotServiceMenuLoaderConfig(): void
    {
        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('serviceMenu', 'service_menu', $wrongConfig);
        $context = Generator::generateSalesChannelContext();

        $this->navigationLoader->expects($this->never())->method('load');

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when navigation loader throws CategoryNotFoundException')]
    public function testLoadReturnsNotFoundWhenCategoryNotFoundExceptionIsThrown(): void
    {
        $serviceCategoryId = Uuid::randomHex();

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new ServiceMenuLoaderConfig();
        $requirement = new DataRequirement('serviceMenu', 'service_menu', $config);
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setServiceCategoryId($serviceCategoryId);

        $this->navigationLoader
            ->method('load')
            ->willThrowException(new CategoryNotFoundException($serviceCategoryId));

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }
}
