<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\FindVariant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
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
        /**
         * @var ProductEntity $variant
         */
        $variant = new ProductEntity();
        $variant->setId(Uuid::randomHex());
        $response = new FindProductVariantRouteResponse(new FoundCombination($variant, []));
        $foundCombination = $response->getFoundCombination();

        static::assertSame($variant->getId(), $foundCombination->getVariantId());
        static::assertSame([], $foundCombination->getOptions());
    }
}
