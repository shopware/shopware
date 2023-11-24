<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Util;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Util\AfterSort;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\Util\AfterSort
 */
class AfterSortTest extends TestCase
{
    /**
     * @param array<CategoryEntity> $elements
     * @param array<string> $correctOrder
     *
     * @dataProvider afterSortProvider
     */
    public function testAfterSort(array $elements, array $correctOrder): void
    {
        $sortedElements = AfterSort::sort($elements, 'afterCategoryId');

        $result = [];
        foreach ($sortedElements as $key => $value) {
            $result[] = $key;
        }

        static::assertEquals($correctOrder, $result);
    }

    private function createCategoryEntity(string $id, ?string $afterCategoryId): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setId($id);
        $category->setAfterCategoryId($afterCategoryId);

        return $category;
    }

    public function afterSortProvider(): \Generator
    {
        $uuid1 = Uuid::randomHex();
        $uuid2 = Uuid::randomHex();
        $uuid3 = Uuid::randomHex();
        $uuid4 = Uuid::randomHex();
        $uuid5 = Uuid::randomHex();

        yield 'Test if order do not changes' => [
            [
                $this->createCategoryEntity($uuid1, null),
                $this->createCategoryEntity($uuid2, $uuid1),
                $this->createCategoryEntity($uuid3, $uuid2),
                $this->createCategoryEntity($uuid4, $uuid3),
                $this->createCategoryEntity($uuid5, $uuid4),
            ],
            [
                $uuid1,
                $uuid2,
                $uuid3,
                $uuid4,
                $uuid5,
            ],
        ];

        yield 'Check if sorts correctly' => [
            [
                $this->createCategoryEntity($uuid4, $uuid3),
                $this->createCategoryEntity($uuid5, $uuid4),
                $this->createCategoryEntity($uuid3, $uuid2),
                $this->createCategoryEntity($uuid2, $uuid1),
                $this->createCategoryEntity($uuid1, null),
            ],
            [
                $uuid1,
                $uuid2,
                $uuid3,
                $uuid4,
                $uuid5,
            ],
        ];

        // 2 chains uuid1-uuid2-uuid3 uuid4-uuid5
        yield 'Check if sorts correctly for missing element' => [
            [
                $this->createCategoryEntity($uuid4, Uuid::randomHex()),
                $this->createCategoryEntity($uuid5, $uuid4),
                $this->createCategoryEntity($uuid3, $uuid2),
                $this->createCategoryEntity($uuid2, $uuid1),
                $this->createCategoryEntity($uuid1, null),
            ],
            [
                $uuid1,
                $uuid2,
                $uuid3,
                $uuid4,
                $uuid5,
            ],
        ];

    }
}
