<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Exception\InvalidFileGeneratorTypeException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(InvalidFileGeneratorTypeException::class)]
class InvalidFileGeneratorTypeExceptionTest extends TestCase
{
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testConstruct(): void
    {
        $exception = new InvalidFileGeneratorTypeException('foo');

        static::assertSame('Unable to find a file generator with type "foo"', $exception->getMessage());
        static::assertSame('DOCUMENT__INVALID_FILE_GENERATOR_TYPE', $exception->getErrorCode());
        static::assertSame(400, $exception->getStatusCode());
    }
}
