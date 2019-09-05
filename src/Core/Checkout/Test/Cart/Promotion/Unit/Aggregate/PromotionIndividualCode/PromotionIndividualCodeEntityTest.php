<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Aggregate\PromotionIndividualCode;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeEntity;
use Shopware\Core\Checkout\Promotion\Exception\CodeAlreadyRedeemedException;

class PromotionIndividualCodeEntityTest extends TestCase
{
    /**
     * This test verifies that our payload is
     * correctly built when setting the code as "redeemed".
     * We need this data as "soft" reference to the order,
     * line item and everything that might be important.
     *
     * @test
     * @group promotions
     */
    public function testRedeemedPayload(): void
    {
        $entity = new PromotionIndividualCodeEntity();
        $entity->setCode('my-code-123');
        $entity->setRedeemed('O-123', '1', 'John Doe');

        $expected = [
            'orderId' => 'O-123',
            'customerId' => '1',
            'customerName' => 'John Doe',
        ];

        static::assertEquals($expected, $entity->getPayload());
    }

    /**
     * This test verifies that we must not be able to mark
     * an individual code as redeemed more than once.
     * We set our code redeemed twice and verify that we get
     * our expected exception.
     *
     * @test
     * @group promotions
     *
     * @throws CodeAlreadyRedeemedException
     */
    public function testAlreadyRedeemedThrowsException(): void
    {
        $this->expectException(CodeAlreadyRedeemedException::class);

        $entity = new PromotionIndividualCodeEntity();
        $entity->setCode('my-code-123');
        $entity->setRedeemed('O-123', '1', 'John Doe');
        $entity->setRedeemed('O-555', '2', 'Jane Doe');
    }

    /**
     * This test verifies that we do not fire an exception if
     * the exactly same data is used again.
     * This avoids any troubles when re-applying or re-saving
     * data in a workflow with multiple iterations.
     *
     * @test
     * @group promotions
     *
     * @throws CodeAlreadyRedeemedException
     */
    public function testAlreadyRedeemedIsOkWithSameData(): void
    {
        $entity = new PromotionIndividualCodeEntity();
        $entity->setCode('my-code-123');
        $entity->setRedeemed('O-123', '1', 'John Doe');
        $entity->setRedeemed('O-123', '1', 'John Doe');

        $expected = [
            'orderId' => 'O-123',
            'customerId' => '1',
            'customerName' => 'John Doe',
        ];

        static::assertEquals($expected, $entity->getPayload());
    }
}
