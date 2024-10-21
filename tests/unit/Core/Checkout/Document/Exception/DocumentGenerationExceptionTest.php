<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DocumentGenerationException::class)]
class DocumentGenerationExceptionTest extends TestCase
{
    public function testConstruct(): void
    {
        $exception = new DocumentGenerationException('test');

        static::assertSame('Unable to generate document. test', $exception->getMessage());
        static::assertSame('DOCUMENT__GENERATION_ERROR', $exception->getErrorCode());
        static::assertSame(400, $exception->getStatusCode());
    }
}
