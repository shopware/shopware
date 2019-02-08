<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use ArrayAccess;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\ArraySubset;
use PHPUnit\Util\InvalidArgumentHelper;

/**
 * @deprecated just a polyfill to remove the noise
 */
trait AssertArraySubsetBehaviour
{
    /**
     * @deprecated to be removed in PHPUnit 9
     */
    public function silentAssertArraySubset($subset, $array, bool $checkForObjectIdentity = false, string $message = ''): void
    {
        if (!(\is_array($subset) || $subset instanceof ArrayAccess)) {
            throw InvalidArgumentHelper::factory(
                1,
                'array or ArrayAccess'
            );
        }

        if (!(\is_array($array) || $array instanceof ArrayAccess)) {
            throw InvalidArgumentHelper::factory(
                2,
                'array or ArrayAccess'
            );
        }

        $constraint = new ArraySubset($subset, $checkForObjectIdentity);

        Assert::assertThat($array, $constraint, $message);
    }
}
