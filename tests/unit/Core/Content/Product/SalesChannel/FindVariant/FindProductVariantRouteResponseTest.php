<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\FindVariant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FoundCombination;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(FindProductVariantRouteResponse::class)]
class FindProductVariantRouteResponseTest extends TestCase
{
    public function testInstantiate(): void
    {
        $id = Uuid::randomHex();
        $response = new FindProductVariantRouteResponse(new FoundCombination($id, []));
        $foundCombination = $response->getFoundCombination();

        static::assertSame($id, $foundCombination->getVariantId());
        static::assertSame([], $foundCombination->getOptions());
    }
}
