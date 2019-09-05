<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Exception\PromotionCodeNotFoundException;

class PromotionCodeNotFoundExceptionTest extends TestCase
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
        $exception = new PromotionCodeNotFoundException('MY-CODE-123');

        static::assertEquals('Promotion Code "MY-CODE-123" has not been found!', $exception->getMessage());
    }

    /**
     * This test verifies that our error code is correct
     *
     * @test
     * @group promotions
     */
    public function testErrorCode(): void
    {
        $exception = new PromotionCodeNotFoundException('');

        static::assertEquals('CHECKOUT__CODE_NOT_FOUND', $exception->getErrorCode());
    }

    /**
     * This test verifies that our error code is correct
     *
     * @test
     * @group promotions
     */
    public function testStatusCode(): void
    {
        $exception = new PromotionCodeNotFoundException('');

        static::assertEquals(400, $exception->getStatusCode());
    }
}
