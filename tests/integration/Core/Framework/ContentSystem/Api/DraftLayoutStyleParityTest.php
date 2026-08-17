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
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class DraftLayoutStyleParityTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('the draft decode path yields the same style shape the write boundary yields for the same raw element')]
    public function testDraftDecodeMatchesWriteBoundaryStyle(): void
    {
        // A partially specified breakpoint map of an option that declares a default: the normalizer fills the
        // missing breakpoints, so a path that skipped it would produce a visibly different map.
        $raw = [[
            'id' => Uuid::randomHex(),
            'component' => 'Sw:Content:Text',
            'properties' => ['text' => '<p>Parity</p>'],
            'style' => ['align-self' => ['xs' => 'center']],
        ]];

        $draftStyle = static::getContainer()->get(DraftLayoutDecoder::class)->decode($raw)[0]->style->toArray();

        $stored = static::getContainer()->get(LayoutWriteBoundary::class)
            ->apply(static::getContainer()->get(StoredTreeCodec::class)->decode($raw));
        $writtenStyle = $stored->roots[0]->style->toArray();

        static::assertSame($writtenStyle, $draftStyle);

        // Guards the equivalence against being vacuously true on an untouched map.
        static::assertIsArray($draftStyle['align-self']);
        static::assertSame(Breakpoint::values(), array_keys($draftStyle['align-self']));
    }
}
