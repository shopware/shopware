<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(GenericCartError::class)]
class GenericCartErrorTest extends TestCase
{
    /**
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('errorProvider')]
    public function testConstruct(
        array $parameters,
        int $level,
        bool $blockOrder,
        bool $persistent,
        bool $blockResubmit,
    ): void {
        $error = new GenericCartError(
            id: 'generic-id',
            messageKey: 'generic-message-key',
            parameters: $parameters,
            level: $level,
            blockOrder: $blockOrder,
            persistent: $persistent,
            blockResubmit: $blockResubmit,
        );

        static::assertSame('', $error->getMessage());
        static::assertSame('generic-id', $error->getId());
        static::assertSame('generic-message-key', $error->getMessageKey());
        static::assertSame($level, $error->getLevel());
        static::assertSame($blockOrder, $error->blockOrder());
        static::assertSame($persistent, $error->isPersistent());
        static::assertSame($blockResubmit, $error->blockResubmit());
        static::assertSame($parameters, $error->getParameters());
    }

    public static function errorProvider(): \Generator
    {
        yield 'blocking error' => [
            ['foo' => 'bar'],
            Error::LEVEL_ERROR,
            true,
            true,
            true,
        ];

        yield 'non-blocking notice' => [
            [],
            Error::LEVEL_NOTICE,
            false,
            false,
            false,
        ];

        yield 'blocks the order but not the resubmit' => [
            ['count' => 3],
            Error::LEVEL_WARNING,
            true,
            false,
            false,
        ];
    }
}
