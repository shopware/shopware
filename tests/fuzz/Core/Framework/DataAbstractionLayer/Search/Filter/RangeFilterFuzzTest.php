<?php declare(strict_types=1);

namespace Shopware\Tests\Fuzz\Core\Framework\DataAbstractionLayer\Search\Filter;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * Property-based companion to RangeFilterTest: instead of a handful of example inputs, this
 * generates a wide space of key/value pairs and checks the constructor's validation invariant
 * holds for all of them. See .agents/skills/shopware-fuzz-tests for the pattern this follows.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(RangeFilter::class)]
class RangeFilterFuzzTest extends TestCase
{
    use TestTrait;

    private const VALID_KEYS = [RangeFilter::LTE, RangeFilter::LT, RangeFilter::GTE, RangeFilter::GT];

    /**
     * RangeFilter validates each parameter independently (no cross-key logic in the
     * constructor loop), so a single key/value pair already exercises the full invariant:
     * construction must either accept the pair unchanged, or throw
     * DataAbstractionLayerException. It must never accept an invalid pair silently, and it
     * must never throw anything else.
     */
    public function testConstructionNeverSilentlyAcceptsInvalidInput(): void
    {
        $this->forAll(
            Generators::elements(RangeFilter::LTE, RangeFilter::LT, RangeFilter::GTE, RangeFilter::GT, 'foo', 'bar', ''),
            Generators::oneOf(
                Generators::string(),
                Generators::choose(-1000, 1000),
                // wrong type entirely, same as an array/object would be - kept scalar so it
                // doesn't also trip the unrelated "Array to string conversion" PHP warning
                // that RangeFilter's own exception-message sprintf() emits for non-scalar values
                Generators::bool(),
                Generators::float(),
                Generators::constant(null),
                Generators::constant('')
            )
        )->then(function (string $key, mixed $value): void {
            $isValidKey = \in_array($key, self::VALID_KEYS, true);
            $isValidValue = $value === null || ((\is_numeric($value) || \is_string($value)) && $value !== '');

            if (!$isValidKey || !$isValidValue) {
                try {
                    /** @phpstan-ignore argument.type (for test purpose) */
                    new RangeFilter('price', [$key => $value]);
                    static::fail(\sprintf('Expected a DataAbstractionLayerException for key "%s" and value %s.', $key, \var_export($value, true)));
                } catch (DataAbstractionLayerException) {
                }

                return;
            }

            $filter = new RangeFilter('price', [$key => $value]);

            static::assertTrue($filter->hasParameter($key));
            static::assertSame($value, $filter->getParameter($key));
            static::assertSame(['price'], $filter->getFields());
        });
    }
}
