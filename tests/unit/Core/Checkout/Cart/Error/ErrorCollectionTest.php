<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;

/**
 * @internal
 */
#[CoversClass(ErrorCollection::class)]
class ErrorCollectionTest extends TestCase
{
    public function testHashing(): void
    {
        $collection = new ErrorCollection();

        static::assertSame('', $collection->getUniqueHash());

        $collection->add(new GenericCartError('12', 'asd', [], Error::LEVEL_ERROR, false, false, false));

        static::assertSame('8412c377d151321a', $collection->getUniqueHash());
    }
}
