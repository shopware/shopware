<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;

class ErrorCollectionTest extends TestCase
{
    public function testErrorTypes(): void
    {
        $errors = new ErrorCollection([
            TestError::error(),
            TestError::error(),
            TestError::error(),
            TestError::error(),
            TestError::warn(),
            TestError::warn(),
            TestError::warn(),
            TestError::notice(),
            TestError::notice(),
            TestError::unknown(),
        ]);

        static::assertCount(4, $errors->getErrors());
        static::assertCount(3, $errors->getWarnings());
        static::assertCount(2, $errors->getNotices());
        static::assertCount(1, $errors->filterByErrorLevel(TestError::LEVEL_UNKNOWN));
        static::assertCount(10, $errors->getElements());
    }

    public function testEmptyDoesNotThrow(): void
    {
        $errors = new ErrorCollection();

        static::assertCount(0, $errors->getErrors());
        static::assertCount(0, $errors->getWarnings());
        static::assertCount(0, $errors->getNotices());
        static::assertCount(0, $errors->getElements());
    }
}
