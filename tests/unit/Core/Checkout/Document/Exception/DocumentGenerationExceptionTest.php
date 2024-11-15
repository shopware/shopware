<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DocumentGenerationException::class)]
class DocumentGenerationExceptionTest extends TestCase
{
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testConstruct(): void
    {
        $exception = new DocumentGenerationException('test');

        static::assertSame('Unable to generate document. test', $exception->getMessage());
        static::assertSame('DOCUMENT__GENERATION_ERROR', $exception->getErrorCode());
        static::assertSame(400, $exception->getStatusCode());
    }
}
