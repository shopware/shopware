<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Exception\InvalidExtensionRatingValueException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package merchant-services
 *
 * @internal
 *
 * @covers \Shopware\Core\Framework\Store\Exception\InvalidExtensionRatingValueException
 */
class InvalidExtensionRatingValueExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__INVALID_EXTENSION_RATING_VALUE',
            (new InvalidExtensionRatingValueException(1))->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_BAD_REQUEST,
            (new InvalidExtensionRatingValueException(1))->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'Invalid rating value 10. The value must correspond to a number in the interval from 1 to 5.',
            (new InvalidExtensionRatingValueException(10))->getMessage()
        );
    }
}
