<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Error\ErrorCollection;
use Shopware\Core\Content\ProductExport\Error\XmlValidationError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ErrorCollection::class)]
class ErrorCollectionTest extends TestCase
{
    public function testAddKeysTheErrorByItsId(): void
    {
        $error = new XmlValidationError('export-id');
        $collection = new ErrorCollection();

        $collection->add($error);

        static::assertSame($error, $collection->get($error->getId()));
    }

    public function testSetIgnoresTheGivenKeyInFavourOfTheId(): void
    {
        $error = new XmlValidationError('export-id');
        $collection = new ErrorCollection();

        $collection->set('something-else', $error);

        static::assertNull($collection->get('something-else'));
        static::assertSame($error, $collection->get($error->getId()));
    }

    public function testApiAlias(): void
    {
        static::assertSame('product_export_error', (new ErrorCollection())->getApiAlias());
    }
}
