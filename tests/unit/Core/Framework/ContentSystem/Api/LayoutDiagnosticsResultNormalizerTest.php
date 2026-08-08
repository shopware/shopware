<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\LayoutDiagnosticsResultNormalizer;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutDiagnosticsResultNormalizer::class)]
class LayoutDiagnosticsResultNormalizerTest extends TestCase
{
    #[DataProvider('normalizesCandidateConfigCompletePerOriginProvider')]
    #[TestDox('normalizes a candidate configComplete per its origin, regardless of the constructed value')]
    public function testNormalizesCandidateConfigCompletePerOrigin(CandidateOrigin $origin, bool $constructed, ?bool $expected): void
    {
        $candidate = new ResolutionCandidate(origin: $origin, contextKey: 'product', configComplete: $constructed);
        $resolution = new PropertyResolution('product', PropertyKind::Reference, false, null, null, 'Some\\Fqcn', $candidate);

        $normalized = (new LayoutDiagnosticsResultNormalizer())->normalizeResolutions(['el-1' => [$resolution]]);

        static::assertSame($expected, $normalized['el-1'][0]['resolved']['configComplete']);
    }

    /**
     * @return iterable<string, array{CandidateOrigin, bool, ?bool}>
     */
    public static function normalizesCandidateConfigCompletePerOriginProvider(): iterable
    {
        // Parent is pinned false per the wire contract, so a Parent constructed with configComplete=true can
        // never leak a true into the response schema.
        yield 'parent pins false even when constructed true' => [CandidateOrigin::Parent, true, false];
        yield 'parent stays false when constructed false' => [CandidateOrigin::Parent, false, false];
        yield 'loader passes through true' => [CandidateOrigin::Loader, true, true];
        yield 'loader passes through false' => [CandidateOrigin::Loader, false, false];
        yield 'stored is null even when constructed true' => [CandidateOrigin::Stored, true, null];
        yield 'stored is null when constructed false' => [CandidateOrigin::Stored, false, null];
    }
}
