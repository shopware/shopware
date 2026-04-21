<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Detail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult;

/**
 * @internal
 */
#[CoversClass(AvailableCombinationResult::class)]
class AvailableCombinationResultTest extends TestCase
{
    public function testFilterByKnownOptionIdsStripsUnknownOptions(): void
    {
        $result = new AvailableCombinationResult();
        $result->addCombination(['missing-color', 'size-m'], true);
        $result->addCombination(['missing-color', 'size-l'], false);

        $filtered = $result->filterByKnownOptionIds(['size-m' => true, 'size-l' => true]);

        static::assertTrue($filtered->hasCombination(['size-m']));
        static::assertTrue($filtered->isAvailable(['size-m']));

        static::assertTrue($filtered->hasCombination(['size-l']));
        static::assertFalse($filtered->isAvailable(['size-l']));

        // The missing color option id must not linger in the filtered result.
        static::assertFalse($filtered->hasOptionId('missing-color'));
    }

    public function testFilterByKnownOptionIdsMergesCollapsedCombinationsAsAvailableWhenAnyIsAvailable(): void
    {
        $result = new AvailableCombinationResult();
        // Two variants collapse to the same normalized hash once the unknown
        // "missing-color" option is stripped.
        $result->addCombination(['missing-color', 'size-m'], false);
        $result->addCombination(['missing-color-2', 'size-m'], true);

        $filtered = $result->filterByKnownOptionIds(['size-m' => true]);

        static::assertTrue($filtered->hasCombination(['size-m']));
        static::assertTrue(
            $filtered->isAvailable(['size-m']),
            'Collapsed combinations must report as available when any source combination was available.'
        );
    }

    public function testFilterByKnownOptionIdsDropsCombinationsWithoutKnownOptions(): void
    {
        $result = new AvailableCombinationResult();
        $result->addCombination(['only-missing'], true);

        $filtered = $result->filterByKnownOptionIds(['size-m' => true]);

        static::assertSame([], $filtered->getCombinations());
    }

    public function testAddCombinationOrMergesAvailabilityOnHashCollision(): void
    {
        $result = new AvailableCombinationResult();
        $result->addCombination(['size-m'], false);
        $result->addCombination(['size-m'], true);

        static::assertTrue($result->isAvailable(['size-m']));
    }
}
