<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\FindVariant;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FoundCombination;
use Shopware\Core\Framework\Test\IdsCollection;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\FindVariant\FoundCombination
 */
class FoundCombinationTest extends TestCase
{
    public function testInstantiate(): void
    {
        $ids = new IdsCollection();

        $options = [
            $ids->get('groupId1') => $ids->get('optionId1'),
            $ids->get('groupId1') => $ids->get('optionId2'),
            $ids->get('groupId2') => $ids->get('optionId3'),
        ];

        $foundCombo = new FoundCombination($ids->get('variantId'), $options);

        static::assertEquals($ids->get('variantId'), $foundCombo->getVariantId());
        static::assertEquals($options, $foundCombo->getOptions());
    }
}
