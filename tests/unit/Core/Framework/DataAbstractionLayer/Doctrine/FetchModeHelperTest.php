<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Doctrine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;

/**
 * @internal
 */
#[CoversClass(FetchModeHelper::class)]
class FetchModeHelperTest extends TestCase
{
    public function testGroup(): void
    {
        $result = FetchModeHelper::group([
            ['wh' => 'wh-1', 'product' => 'PROD-1', 'stock' => '10'],
            ['wh' => 'wh-1', 'product' => 'PROD-2', 'stock' => '5'],
            ['wh' => 'wh-2', 'product' => 'PROD-3', 'stock' => '20'],
        ]);

        static::assertSame(
            [
                'wh-1' => [
                    ['product' => 'PROD-1', 'stock' => '10'],
                    ['product' => 'PROD-2', 'stock' => '5'],
                ],
                'wh-2' => [
                    ['product' => 'PROD-3', 'stock' => '20'],
                ],
            ],
            $result
        );
    }

    public function testGroupWithMapper(): void
    {
        $result = FetchModeHelper::group(
            [
                ['id' => 'some-set-1', 'entity_name' => 'product'],
                ['id' => 'some-set-1', 'entity_name' => 'customer'],
                ['id' => 'some-set-2', 'entity_name' => 'product'],
            ],
            fn (array $row) => $row['entity_name']
        );

        static::assertSame(
            [
                'some-set-1' => ['product', 'customer'],
                'some-set-2' => ['product'],
            ],
            $result
        );
    }
}
