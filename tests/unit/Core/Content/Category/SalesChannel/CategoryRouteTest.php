<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CategoryRoute::class)]
class CategoryRouteTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testLoadLinkPageType(): void
    {
        $category = $this->buildContentlessCategory(CategoryDefinition::TYPE_LINK);

        $response = $this->loadContentlessCategory($category);

        // Passively asserts that no exception has been thrown
        static::assertSame(CategoryDefinition::TYPE_LINK, $response->getCategory()->getType());
    }

    public function testLoadFolderPageType(): void
    {
        $category = $this->buildContentlessCategory(CategoryDefinition::TYPE_FOLDER);

        $this->expectException(CategoryNotFoundException::class);
        $this->expectExceptionMessage(\sprintf(
            'Category "%s" not found.',
            $this->ids->get('category'),
        ));

        $this->loadContentlessCategory($category);
    }

    private function buildContentlessCategory(string $type): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId($this->ids->create('category'));
        $category->setType($type);

        return $category;
    }

    private function loadContentlessCategory(CategoryEntity $category): CategoryRouteResponse
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $categoryRepositoryMock = $this->createMock(SalesChannelRepository::class);
        $categoryRepositoryMock
            ->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'category',
                1,
                new CategoryCollection([$category]),
                null,
                new Criteria(),
                $salesChannelContext->getContext(),
            ));

        $categoryRoute = new CategoryRoute(
            $categoryRepositoryMock,
            $this->createMock(SalesChannelCmsPageLoaderInterface::class),
            new EntityCmsSlotConfigInheritanceBuilder($this->createMock(Connection::class)),
            new CategoryDefinition(),
            new EventDispatcher(),
        );

        return $categoryRoute->load(
            $this->ids->get('category'),
            new Request(),
            $salesChannelContext,
        );
    }
}
