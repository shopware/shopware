<?php declare(strict_types=1);

namespace Shopware\Core\Test\Constraint;

use Countable;
use PHPUnit\Framework\Constraint\Constraint;
use Shopware\Core\Framework\Log\Package;
use Traversable;

/**
 * Constraint that is true only when the value is an iterable with zero elements.
 *
 * Strict semantics:
 * - Arrays with count() === 0
 * - Objects implementing Countable with count() === 0
 * - Traversable objects with no elements (note: iterating may consume generators)
 *
 * Other types (null, string, bool, numbers, non-traversable objects) are NOT considered empty.
 *
 * @internal
 */
#[Package('framework')]
final class StrictIsEmpty extends Constraint
{
    public function toString(): string
    {
        return 'is strictly empty (iterable with zero elements)';
    }

    protected function matches(mixed $other): bool
    {
        // Arrays
        if (\is_array($other)) {
            return $other === [];
        }

        // Countable objects
        if ($other instanceof \Countable) {
            return \count($other) === 0;
        }

        // Traversable that is not Countable: iterate once to detect any element
        if ($other instanceof \Traversable) {
            foreach ($other as $_) {
                return false;
            }

            return true;
        }

        // Anything else is not considered strictly empty
        return false;
    }
}
