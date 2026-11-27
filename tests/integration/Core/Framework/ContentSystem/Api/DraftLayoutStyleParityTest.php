<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteBoundary;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;

/**
 * The container-wired behavioral half of the style parity invariant: resolves `DraftLayoutDecoder` and
 * `LayoutWriteBoundary` from DI and compares their normalized output for one exercised option, so the wired
 * instances demonstrably run. The definition pin and the normalizer-behavior half — one hand-built instance
 * feeding both paths — are the unit test of the same name.
 *
 * @internal
 */
#[Package('framework')]
class DraftLayoutStyleParityTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('yields the same style shape from the container-wired draft decode path as the container-wired write boundary')]
    public function testContainerWiredDraftDecodeMatchesWriteBoundaryStyle(): void
    {
        // A scalar exercises the normalizer that wraps it in a breakpoint map.
        $raw = [[
            'id' => 'parity-element',
            'component' => TestElementTypeLoader::DEFAULTED_PRIMITIVE,
            'properties' => ['headline' => 'Parity'],
            'style' => ['align-self' => 'center'],
        ]];

        $draftStyle = static::getContainer()->get(DraftLayoutDecoder::class)
            ->decode($raw)[0]->style->toArray();

        $written = static::getContainer()->get(LayoutWriteBoundary::class)
            ->apply(static::getContainer()->get(StoredTreeCodec::class)->decode($raw));
        $writtenStyle = $written->roots[0]->style->toArray();

        static::assertSame($writtenStyle, $draftStyle);

        static::assertIsArray($draftStyle['align-self']);
        static::assertSame(Breakpoint::values(), array_keys($draftStyle['align-self']));
    }
}
