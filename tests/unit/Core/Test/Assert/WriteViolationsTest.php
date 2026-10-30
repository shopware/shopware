<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Assert;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Assert\WriteViolations;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WriteViolations::class)]
class WriteViolationsTest extends TestCase
{
    public function testAWriteThatThrowsNothingIsAccepted(): void
    {
        WriteViolations::assertAccepted(static function (): void {});
    }

    public function testARejectedWriteFailsTheAcceptedAssertion(): void
    {
        $this->expectException(AssertionFailedError::class);

        WriteViolations::assertAccepted(fn () => throw $this->rejection(['A']));
    }

    public function testARejectedWriteHasTheCodesOfAllItsViolationsInOrder(): void
    {
        WriteViolations::assertRejected(['A', 'B', 'C'], fn () => throw $this->rejection(['A', 'B'], ['C']));
    }

    public function testARejectedWriteWithOtherCodesFailsTheRejectedAssertion(): void
    {
        $this->expectException(AssertionFailedError::class);

        WriteViolations::assertRejected(['A'], fn () => throw $this->rejection(['B']));
    }

    public function testAnAcceptedWriteFailsTheRejectedAssertion(): void
    {
        $this->expectException(AssertionFailedError::class);

        WriteViolations::assertRejected(['A'], static function (): void {});
    }

    /**
     * @param list<string> ...$codesPerException
     */
    private function rejection(array ...$codesPerException): WriteException
    {
        $exception = new WriteException();
        foreach ($codesPerException as $codes) {
            $violations = new ConstraintViolationList();
            foreach ($codes as $code) {
                $violations->add(new ConstraintViolation('', null, [], null, '/value', null, null, $code));
            }
            $exception->add(new WriteConstraintViolationException($violations, '/0'));
        }

        return $exception;
    }
}
