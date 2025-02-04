<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Address\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\SalutationMissingError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SalutationMissingError::class)]
class SalutationMissingErrorTest extends TestCase
{
    public function testGetMessageKeyIsIdenticalToGetId(): void
    {
        $error = new class extends SalutationMissingError {
            public function getId(): string
            {
                return 'salutation-missing';
            }
        };

        static::assertSame('salutation-missing', $error->getMessageKey());
        static::assertSame(10, $error->getLevel());
        static::assertTrue($error->blockOrder());
    }
}
