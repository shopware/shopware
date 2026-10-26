<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentExceptionHandler;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentExceptionHandler::class)]
class ProductDocumentExceptionHandlerTest extends TestCase
{
    #[DataProvider('duplicateDocumentMessages')]
    public function testDuplicateDocumentBecomesABadRequest(string $message): void
    {
        $exception = (new ProductDocumentExceptionHandler())->matchException(new \RuntimeException($message));

        static::assertEquals(ProductException::duplicateDocument(), $exception);
    }

    public static function duplicateDocumentMessages(): \Generator
    {
        yield 'MySQL names the key with its table' => [
            'An exception occurred while executing a query: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'\x01-\x02\' for key \'product_document.uniq.product_document.product_language_media\'',
        ];

        yield 'MariaDB names the key alone' => [
            'An exception occurred while executing a query: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'\x01-\x02\' for key \'uniq.product_document.product_language_media\'',
        ];
    }

    public function testDuplicateKeysOfOtherTablesAreLeftAlone(): void
    {
        $exception = new \RuntimeException('An exception occurred while executing a query: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'\x01\' for key \'product_media.PRIMARY\'');

        static::assertNull((new ProductDocumentExceptionHandler())->matchException($exception));
    }

    public function testPriority(): void
    {
        static::assertSame(ExceptionHandlerInterface::PRIORITY_DEFAULT, (new ProductDocumentExceptionHandler())->getPriority());
    }
}
