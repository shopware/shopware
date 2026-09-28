<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Violation::class)]
class ViolationTest extends TestCase
{
    /**
     * The named constructor is the single mint site for this defect — the write boundary, the draft decoder
     * and the diagnostics report all reach it — so the wording is pinned here rather than at each of them.
     */
    #[TestDox('mints the duplicate-id defect with one wording for every caller')]
    public function testDuplicateElementIdCarriesItsCodeAndWording(): void
    {
        $violation = Violation::duplicateElementId('root-1');

        static::assertSame(ViolationCode::DuplicateElementId, $violation->code);
        static::assertSame('root-1', $violation->elementId);
        static::assertNull($violation->key);
        static::assertSame('Element id "root-1" is not unique across the layout.', $violation->message);
        static::assertSame([], $violation->candidates);
    }

    #[TestDox('derives scope and severity from the violation code')]
    public function testScopeAndSeverityComeFromTheCode(): void
    {
        $violation = Violation::duplicateElementId('root-1');

        static::assertSame(ViolationCode::DuplicateElementId->scope(), $violation->scope());
        static::assertSame(ViolationCode::DuplicateElementId->severity(), $violation->severity());
    }
}
