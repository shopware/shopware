<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsDataDefinition;
use Shopware\Core\Checkout\Promotion\PromotionEntity;

/**
 * @internal
 */
#[CoversClass(CartPromotionsDataDefinition::class)]
class CartPromotionsDataDefinitionTest extends TestCase
{
    /**
     * This test verifies that automatic promotions are returned with an empty string as code value within its tuple object.
     * We add one promotion without code, and verify the single tuple that will be generated.
     */
    #[Group('promotions')]
    public function testAutomaticPromotionHasEmptyCode(): void
    {
        $definition = new CartPromotionsDataDefinition();
        $definition->addAutomaticPromotions([new PromotionEntity()]);

        $tuples = $definition->getPromotionCodeTuples();

        static::assertSame('', $tuples[0]->getCode());
    }

    /**
     * This test verifies that promotions with code get the correct code within its tuple object.
     * We add one promotion with code, and verify the single tuple that will be generated.
     */
    #[Group('promotions')]
    public function testCodePromotionHasCorrectCode(): void
    {
        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('codeA', [new PromotionEntity()]);

        $tuples = $definition->getPromotionCodeTuples();

        static::assertSame('codeA', $tuples[0]->getCode());
    }

    /**
     * This test verifies we get two tuple objects for a code if we add two promotions for it.
     */
    #[Group('promotions')]
    public function testMultiplePromotionForCode(): void
    {
        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('codeA', [new PromotionEntity(), new PromotionEntity()]);

        $tuples = $definition->getPromotionCodeTuples();

        static::assertSame('codeA', $tuples[0]->getCode());
        static::assertSame('codeA', $tuples[1]->getCode());
    }

    /**
     * This test verifies that we can retrieve all added promotions as 1 single tuple list.
     * This should combine all code promotions and the automatic promotions
     */
    #[Group('promotions')]
    public function testGetPromotionCodeTuplesAll(): void
    {
        $promotion1 = new PromotionEntity();
        $promotion2 = new PromotionEntity();
        $promotion3 = new PromotionEntity();

        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('codeA', [$promotion1]);
        $definition->addCodePromotions('codeB', [$promotion2]);
        $definition->addAutomaticPromotions([$promotion3]);

        static::assertCount(3, $definition->getPromotionCodeTuples());
    }

    /**
     * This test verifies that we get the correct flat list of added codes from the definition.
     * This has to return the codes, even if the promotion list is empty for a code.
     */
    #[Group('promotions')]
    public function testGetAllCodes(): void
    {
        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('codeA', []);
        $definition->addCodePromotions('codeB', []);
        $definition->addAutomaticPromotions([]);

        static::assertSame(['codeA', 'codeB'], $definition->getAllCodes());
    }

    /**
     * This test verifies that we can successfully remove a code including the promotions.
     * We add two codes with a sum of four promotions and ensure we have two codes in the end.
     */
    #[Group('promotions')]
    public function testRemoveCode(): void
    {
        $promotion1 = new PromotionEntity();
        $promotion2 = new PromotionEntity();
        $promotion3 = new PromotionEntity();
        $promotion4 = new PromotionEntity();

        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('codeA', [$promotion1, $promotion2]);
        $definition->addCodePromotions('codeB', [$promotion3]);
        $definition->addAutomaticPromotions([$promotion4]);

        $definition->removeCode('codeB');

        static::assertCount(3, $definition->getPromotionCodeTuples());
        static::assertSame(['codeA'], $definition->getAllCodes());
    }

    /**
     * This test verifies that our hasCode returns true if we have an entry for the code.
     */
    #[Group('promotions')]
    public function testHasCodeIsTrueEvenIfEmpty(): void
    {
        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('codeA', []);

        static::assertTrue($definition->hasCode('codeA'));
    }

    /**
     * This test verifies that our hasCode returns false if we don't have an entry for the code.
     */
    #[Group('promotions')]
    public function testHasCodeIsFalse(): void
    {
        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('codeA', []);

        static::assertFalse($definition->hasCode('ABC'));
    }

    /**
     * This test verifies that we cast any code that is based on a number when creating tuples.
     * Otherwise, PHP would automatically use integer which would lead to an exception.
     */
    #[Group('promotions')]
    public function testIntegerCodeIsCastedWhenBuildingTuples(): void
    {
        $promotion1 = new PromotionEntity();

        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('100', [$promotion1]);

        $tuple = $definition->getPromotionCodeTuples()[0];

        static::assertSame('100', $tuple->getCode());
    }
}
