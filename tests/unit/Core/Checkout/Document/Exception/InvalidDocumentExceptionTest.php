<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(InvalidDocumentException::class)]
class InvalidDocumentExceptionTest extends TestCase
{
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testConstruct(): void
    {
        $exception = new InvalidDocumentException('test');

        static::assertSame('The document with id "test" is invalid or could not be found.', $exception->getMessage());
        static::assertSame('DOCUMENT__INVALID_DOCUMENT_ID', $exception->getErrorCode());
        static::assertSame(400, $exception->getStatusCode());
    }
}
