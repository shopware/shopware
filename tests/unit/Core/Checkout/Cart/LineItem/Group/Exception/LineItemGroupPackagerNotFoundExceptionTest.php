<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupPackagerNotFoundException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LineItemGroupPackagerNotFoundException::class)]
class LineItemGroupPackagerNotFoundExceptionTest extends TestCase
{
    /**
     * This test verifies that our provided code is correctly
     * visible in the resulting exception message.
     */
    #[Group('lineitemgroup')]
    public function testCodeInMessage(): void
    {
        $exception = new LineItemGroupPackagerNotFoundException('COUNT');

        static::assertEquals('Packager "COUNT" has not been found!', $exception->getMessage());
    }

    /**
     * This test verifies that our error code is correct
     */
    #[Group('lineitemgroup')]
    public function testErrorCode(): void
    {
        $exception = new LineItemGroupPackagerNotFoundException('');

        static::assertEquals('CHECKOUT__GROUP_PACKAGER_NOT_FOUND', $exception->getErrorCode());
    }

    /**
     * This test verifies that our error code is correct
     */
    #[Group('lineitemgroup')]
    public function testStatusCode(): void
    {
        $exception = new LineItemGroupPackagerNotFoundException('');

        static::assertEquals(400, $exception->getStatusCode());
    }
}
