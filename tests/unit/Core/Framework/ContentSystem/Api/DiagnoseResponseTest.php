<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\DiagnoseResponse;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DiagnoseResponse::class)]
class DiagnoseResponseTest extends TestCase
{
    #[TestDox('normalizes the raw resolutions and the merged report through the factory')]
    public function testNormalizesRawResolutionsAndReport(): void
    {
        $response = DiagnoseResponse::fromReport(
            ['el-1' => [new PropertyResolution('headline', PropertyKind::Primitive, false, 'string', 'hi')]],
            new DiagnosticsReport([new Violation(ViolationCode::DuplicateElementId, 'el-1', null, 'dup')]),
        );

        $decoded = json_decode((string) json_encode($response, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('headline', $decoded['resolutions']['el-1'][0]['key']);
        static::assertFalse($decoded['diagnostics']['wellFormed']);
        static::assertSame(ViolationCode::DuplicateElementId->value, $decoded['diagnostics']['violations'][0]['code']);
    }

    #[TestDox('serializes empty resolutions as a JSON object exposing exactly the two wire keys without leaking apiAlias or extensions')]
    public function testSerializesEmptyResolutionsWithExactlyTheTwoWireKeys(): void
    {
        $response = DiagnoseResponse::fromReport([], new DiagnosticsReport([]));

        $json = (string) json_encode($response, \JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertStringContainsString('"resolutions":{}', $json);
        static::assertSame(['resolutions', 'diagnostics'], array_keys($decoded));
        static::assertArrayNotHasKey('apiAlias', $decoded);
        static::assertArrayNotHasKey('extensions', $decoded);
    }
}
