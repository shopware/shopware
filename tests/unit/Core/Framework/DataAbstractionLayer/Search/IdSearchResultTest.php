<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IdSearchResult::class)]
class IdSearchResultTest extends TestCase
{
    public function testEmptyResultHasNoPrimaryKeyData(): void
    {
        $result = IdSearchResult::fromIds([], new Criteria(), Context::createDefaultContext());

        static::assertSame([], $result->getPrimaryKeyData());
    }

    public function testScalarIdsBecomeIdPayloads(): void
    {
        $result = IdSearchResult::fromIds(['first-id', 'second-id'], new Criteria(), Context::createDefaultContext());

        static::assertSame(
            [['id' => 'first-id'], ['id' => 'second-id']],
            $result->getPrimaryKeyData()
        );
    }

    public function testCompositePrimaryKeysStayUnchanged(): void
    {
        $primaryKeys = [
            ['productId' => 'product-1', 'categoryId' => 'category-1'],
            ['productId' => 'product-2', 'categoryId' => 'category-2'],
        ];
        $result = new IdSearchResult(
            2,
            [
                'product-1-category-1' => ['primaryKey' => $primaryKeys[0], 'data' => []],
                'product-2-category-2' => ['primaryKey' => $primaryKeys[1], 'data' => []],
            ],
            new Criteria(),
            Context::createDefaultContext()
        );

        static::assertSame($primaryKeys, $result->getPrimaryKeyData());
    }
}
