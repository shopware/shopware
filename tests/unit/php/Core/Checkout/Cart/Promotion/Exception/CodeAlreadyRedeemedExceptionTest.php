<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionException;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Promotion\Exception\CodeAlreadyRedeemedException
 */
class CodeAlreadyRedeemedExceptionTest extends TestCase
{
    /**
     * This test verifies that our provided code is correctly
     * visible in the resulting exception message.
     *
     * @group promotions
     */
    public function testCodeInMessage(): void
    {
        $exception = PromotionException::codeAlreadyRedeemed('MY-CODE-123');

        static::assertEquals('Promotion with code "MY-CODE-123" has already been marked as redeemed!', $exception->getMessage());
    }

    /**
     * This test verifies that our error code is correct
     *
     * @group promotions
     */
    public function testErrorCode(): void
    {
        $exception = PromotionException::codeAlreadyRedeemed('');

        static::assertEquals('CHECKOUT__CODE_ALREADY_REDEEMED', $exception->getErrorCode());
    }

    /**
     * This test verifies that our error code is correct
     *
     * @group promotions
     */
    public function testStatusCode(): void
    {
        $exception = PromotionException::codeAlreadyRedeemed('');

        static::assertEquals(400, $exception->getStatusCode());
    }
}
