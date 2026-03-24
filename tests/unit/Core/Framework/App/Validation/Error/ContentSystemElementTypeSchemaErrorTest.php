<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemElementTypeSchemaError;

/**
 * @internal
 */
#[CoversClass(ContentSystemElementTypeSchemaError::class)]
class ContentSystemElementTypeSchemaErrorTest extends TestCase
{
    #[TestDox('formats message with filename and reason')]
    public function testFormatsMessageWithFilenameAndReason(): void
    {
        $error = new ContentSystemElementTypeSchemaError('/path/to/types', 'YAML syntax invalid');

        static::assertSame('Invalid element type schema in "/path/to/types": YAML syntax invalid', $error->getMessage());
    }

    #[TestDox('returns correct message key')]
    public function testReturnsCorrectMessageKey(): void
    {
        $error = new ContentSystemElementTypeSchemaError('file.yaml', 'reason');

        static::assertSame('manifest-invalid-element-type-schema', $error->getMessageKey());
    }
}
