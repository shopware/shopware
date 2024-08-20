<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Content\Category\CategoryService;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CategoryService::class)]
class CategoryServiceTest extends TestCase
{
    /**
     * @var MockObject|Connection
     */
    private $connection;

    /**
     * @var MockObject|SalesChannelRepository
     */
    private $categoryRepository;

    /**
     * @var CategoryService
     */
    private $categoryService;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->categoryRepository = $this->createMock(SalesChannelRepository::class);
        $this->categoryService = new CategoryService($this->connection, $this->categoryRepository);
    }

    public function testIsChildCategory()
    {
        $activeId = Uuid::randomHex();
        $rootId = Uuid::randomHex();

        $result = $this->categoryService->isChildCategory($activeId, '|' . $rootId . '|some|path|', $rootId);
        $this->assertTrue($result);

        $result = $this->categoryService->isChildCategory($activeId, null, $rootId);
        $this->assertFalse($result);

        $result = $this->categoryService->isChildCategory($rootId, '|' . $rootId . '|some|path|', $rootId);
        $this->assertTrue($result);

        $result = $this->categoryService->isChildCategory($activeId, '|' . Uuid::randomHex() . '|some|path|', $rootId);
        $this->assertFalse($result);
    }
}
