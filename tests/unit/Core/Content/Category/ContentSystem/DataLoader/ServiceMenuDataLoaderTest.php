<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
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
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceMenuDataLoader::class)]
class ServiceMenuDataLoaderTest extends TestCase
{
    private NavigationLoaderInterface&Stub $navigationLoader;

    private ServiceMenuDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->navigationLoader = static::createStub(NavigationLoaderInterface::class);
        $this->dataLoader = new ServiceMenuDataLoader($this->navigationLoader, new NavigationAliasResolver());
    }

    #[TestDox('returns service_menu source type identifier')]
    public function testGetRequirementTypeReturnsServiceMenuString(): void
    {
        static::assertSame('service_menu', ServiceMenuDataLoader::getRequirementType());
    }

    #[TestDox('declares CategoryCollection as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->dataLoader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(CategoryCollection::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('loads service menu categories flattened from navigation tree')]
    public function testLoadReturnsFlattenedCategoryCollection(): void
    {
        $serviceCategoryId = 'category-service';
        $categoryA = new CategoryEntity();
        $categoryA->setId('category-alice');
        $categoryA->setUniqueIdentifier('category-alice');
        $categoryB = new CategoryEntity();
        $categoryB->setId('category-bob');
        $categoryB->setUniqueIdentifier('category-bob');

        $tree = new Tree(null, [
            new TreeItem($categoryA, []),
            new TreeItem($categoryB, []),
        ]);

        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setServiceCategoryId($serviceCategoryId);

        $navigationLoader = static::createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($serviceCategoryId, $context, $serviceCategoryId, 1)
            ->willReturn($tree);

        $dataLoader = new ServiceMenuDataLoader($navigationLoader, new NavigationAliasResolver());
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => 'service-navigation']),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->hasData());
        static::assertInstanceOf(CategoryCollection::class, $result->data);
        static::assertCount(2, $result->data);
        static::assertSame($categoryA, $result->data->first());
        static::assertSame($categoryB, $result->data->last());
    }

    #[TestDox('uses explicit rootId input instead of the service-navigation alias')]
    public function testLoadUsesExplicitRootIdInput(): void
    {
        $rootId = 'category-root';
        $category = new CategoryEntity();
        $category->setId('category-alice');
        $category->setUniqueIdentifier('category-alice');

        $tree = new Tree(null, [new TreeItem($category, [])]);

        $context = Generator::generateSalesChannelContext();

        $navigationLoader = static::createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 1)
            ->willReturn($tree);

        $dataLoader = new ServiceMenuDataLoader($navigationLoader, new NavigationAliasResolver());
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => $rootId]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->hasData());
        static::assertInstanceOf(CategoryCollection::class, $result->data);
        static::assertCount(1, $result->data);
    }

    #[TestDox('returns empty cached category collection when tree has no items')]
    public function testLoadReturnsEmptyCachedCollectionWhenTreeHasNoItems(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setServiceCategoryId('category-service');

        $this->navigationLoader->method('load')->willReturn(new Tree(null, []));

        $result = $this->dataLoader->load(
            new LoaderInputs(['rootId' => 'service-navigation']),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->hasData());
        static::assertInstanceOf(CategoryCollection::class, $result->data);
        static::assertCount(0, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns an empty cached CategoryCollection when the service category is not configured, an unset rootId input included')]
    public function testLoadReturnsEmptyCollectionWhenServiceCategoryNotConfigured(): void
    {
        $context = Generator::generateSalesChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader->expects($this->never())->method('load');

        $dataLoader = new ServiceMenuDataLoader($navigationLoader, new NavigationAliasResolver());
        $inputs = (new LoaderInputResolver())->resolve(
            $dataLoader->configSpecification(),
            new ServiceMenuLoaderConfig(),
            [],
        );

        $result = $dataLoader->load($inputs, self::requirement(), $context, new Request());

        static::assertTrue($result->hasData());
        static::assertInstanceOf(CategoryCollection::class, $result->data);
        static::assertCount(0, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when navigation loader throws CategoryNotFoundException')]
    public function testLoadReturnsNotFoundWhenCategoryNotFoundExceptionIsThrown(): void
    {
        $serviceCategoryId = 'category-service';

        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setServiceCategoryId($serviceCategoryId);

        $this->navigationLoader
            ->method('load')
            ->willThrowException(new CategoryNotFoundException($serviceCategoryId));

        $result = $this->dataLoader->load(
            new LoaderInputs(['rootId' => 'service-navigation']),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('serviceMenu', 'service_menu', new ServiceMenuLoaderConfig());
    }
}
