<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Exception\CodeAlreadyRedeemedException;

class CodeAlreadyRedeemedExceptionTest extends TestCase
{
    /**
     * This test verifies that our provided code is correctly
     * visible in the resulting exception message.
     *
     * @test
     * @group promotions
     */
    public function testCodeInMessage(): void
    {
        $exception = new CodeAlreadyRedeemedException('MY-CODE-123');

        static::assertEquals('Promotion with code "MY-CODE-123" has already been marked as redeemed!', $exception->getMessage());
    }

    /**
     * This test verifies that our error code is correct
     *
     * @test
     * @group promotions
     */
    public function testErrorCode(): void
    {
        $exception = new CodeAlreadyRedeemedException('');

        static::assertEquals('CHECKOUT__CODE_ALREADY_REDEEMED', $exception->getErrorCode());
    }

    /**
     * This test verifies that our error code is correct
     *
     * @test
     * @group promotions
     */
    public function testStatusCode(): void
    {
        $exception = new CodeAlreadyRedeemedException('');

        static::assertEquals(400, $exception->getStatusCode());
    }
}
