<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Storybook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Storybook\StorybookHtmlSanitizerException;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorybookHtmlSanitizerException::class)]
class StorybookHtmlSanitizerExceptionTest extends TestCase
{
    public function testCreatesExceptionWithPreviousThrowable(): void
    {
        $previous = new \RuntimeException('Original sanitization failure');

        $exception = StorybookHtmlSanitizerException::unableToSanitize($previous);

        static::assertSame('Storybook HTML could not be sanitized.', $exception->getMessage());
        static::assertSame(0, $exception->getCode());
        static::assertSame($previous, $exception->getPrevious());
    }
}
