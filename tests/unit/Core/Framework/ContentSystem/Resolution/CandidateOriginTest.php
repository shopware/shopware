<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CandidateOrigin::class)]
class CandidateOriginTest extends TestCase
{
    /**
     * The case set is a wire contract: each value is serialized verbatim as a candidate's `origin`, and the
     * exhaustive matches over this enum are what a client reads to decide which wiring to write. A case added
     * or a value renamed fails here rather than at the boundary that serializes it.
     */
    #[TestDox('carries the four candidate origins with their wire values')]
    public function testCasesCarryTheirWireValues(): void
    {
        static::assertSame(
            ['parent', 'loader', 'stored', 'root'],
            array_column(CandidateOrigin::cases(), 'value')
        );
    }
}
