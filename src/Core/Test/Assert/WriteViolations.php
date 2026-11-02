<?php declare(strict_types=1);

namespace Shopware\Core\Test\Assert;

use PHPUnit\Framework\Assert;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @internal
 */
#[Package('framework')]
final class WriteViolations
{
    public static function assertAccepted(callable $write): void
    {
        $rejection = null;

        try {
            $write();
        } catch (WriteException $exception) {
            $rejection = $exception;
        }

        Assert::assertNull($rejection, 'Expected the write to be accepted, but it was rejected: ' . $rejection?->getMessage());
    }

    /**
     * @param non-empty-list<string> $expectedCodes
     */
    public static function assertRejected(array $expectedCodes, callable $write): void
    {
        try {
            $write();
        } catch (WriteException $exception) {
            $codes = [];
            foreach ($exception->getExceptions() as $inner) {
                Assert::assertInstanceOf(WriteConstraintViolationException::class, $inner, $exception->getMessage());
                foreach ($inner->getViolations() as $violation) {
                    $codes[] = $violation->getCode();
                }
            }

            Assert::assertSame($expectedCodes, $codes);

            return;
        }

        Assert::fail('Expected the write to be rejected, but it was accepted.');
    }
}
