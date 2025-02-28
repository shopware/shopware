<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\FindVariant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FoundCombination;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(FoundCombination::class)]
class FoundCombinationTest extends TestCase
{
    public function testInstantiate(): void
    {
        $ids = new IdsCollection();

        $options = [
            $ids->get('groupId1') => $ids->get('optionId1'),
            $ids->get('groupId2') => $ids->get('optionId3'),
        ];

        $productEntity = new ProductEntity();
        $productEntity->setId($ids->get('variantId'));

        $foundCombination = new FoundCombination($productEntity, $options);

        static::assertEquals($ids->get('variantId'), $foundCombination->getVariantId());
        static::assertEquals($options, $foundCombination->getOptions());
    }
}
