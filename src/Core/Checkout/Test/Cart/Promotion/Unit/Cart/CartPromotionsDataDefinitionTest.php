<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsDataDefinition;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCodeTuple;
use Shopware\Core\Checkout\Promotion\PromotionEntity;

class CartPromotionsDataDefinitionTest extends TestCase
{
    /**
     * This test verifies that automatic promotions
     * are returned with an empty string as code value
     * within its tuple object.
     * We add 1 promotion without code, and verify the single tuple
     * that will be generated.
     *
     * @test
     * @group promotions
     */
    public function testAutomaticPromotionHasEmptyCode(): void
    {
        $definition = new CartPromotionsDataDefinition();
        $definition->addAutomaticPromotions([new PromotionEntity()]);

        /** @var PromotionCodeTuple[] $tuples */
        $tuples = $definition->getPromotionCodeTuples();

        static::assertEquals('', $tuples[0]->getCode());
    }

    /**
     * This test verifies that promotions with code get the
     * correct code within its tuple object.
     * We add 1 promotion with code, and verify the single tuple
     * that will be generated.
     *
     * @test
     * @group promotions
     */
    public function testCodePromotionHasCorrectCode(): void
    {
        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('codeA', [new PromotionEntity()]);

        /** @var PromotionCodeTuple[] $tuples */
        $tuples = $definition->getPromotionCodeTuples();

        static::assertEquals('codeA', $tuples[0]->getCode());
    }

    /**
     * This test verifies we get 2 tuple objects for a code
     * if we add 2 promotions for it.
     *
     * @test
     * @group promotions
     */
    public function testMultiplePromotionForCode(): void
    {
        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('codeA', [new PromotionEntity(), new PromotionEntity()]);

        /** @var PromotionCodeTuple[] $tuples */
        $tuples = $definition->getPromotionCodeTuples();

        static::assertEquals('codeA', $tuples[0]->getCode());
        static::assertEquals('codeA', $tuples[1]->getCode());
    }

    /**
     * This test verifies that we can retrieve all added
     * promotions as 1 single tuple list. This should combine all
     * code promotions and the automatic promotions
     *
     * @test
     * @group promotions
     */
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
     * This test verifies that we get the correct flat list
     * of added codes from the definition.
     * This has to return the codes, even if the promotion list is
     * empty for a code.
     *
     * @test
     * @group promotions
     */
    public function testGetAllCodes(): void
    {
        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('codeA', []);
        $definition->addCodePromotions('codeB', []);
        $definition->addAutomaticPromotions([]);

        static::assertEquals(['codeA', 'codeB'], $definition->getAllCodes());
    }

    /**
     * This test verifies that we can successfully remove a code
     * including the promotions. We add 2 codes with a sum of 4 promotions
     * and ensure we have 2 codes in the end.
     *
     * @test
     * @group promotions
     */
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
        static::assertEquals(['codeA'], $definition->getAllCodes());
    }

    /**
     * This test verifies that our hasCode returns
     * true if we have an entry for the code.
     *
     * @test
     * @group promotions
     */
    public function testHasCodeIsTrueEvenIfEmpty(): void
    {
        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('codeA', []);

        static::assertTrue($definition->hasCode('codeA'));
    }

    /**
     * This test verifies that our hasCode returns
     * false if we dont have an entry for the code.
     *
     * @test
     * @group promotions
     */
    public function testHasCodeIsFalse(): void
    {
        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('codeA', []);

        static::assertFalse($definition->hasCode('ABC'));
    }

    /**
     * This test verifies that we cast any code that is based on a number
     * when creating tuples. Otherwise PHP would automatically use INT
     * which would lead to an exception.
     *
     * @test
     * @group promotions
     */
    public function testIntegerCodeIsCastedWhenBuildingTuples(): void
    {
        $promotion1 = new PromotionEntity();

        $definition = new CartPromotionsDataDefinition();
        $definition->addCodePromotions('100', [$promotion1]);

        /** @var PromotionCodeTuple $tuple */
        $tuple = $definition->getPromotionCodeTuples()[0];

        static::assertEquals('100', $tuple->getCode());
    }
}
