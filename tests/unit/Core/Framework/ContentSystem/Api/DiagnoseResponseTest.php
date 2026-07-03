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

/**
 * @internal
 */
#[CoversClass(DiagnoseResponse::class)]
class DiagnoseResponseTest extends TestCase
{
    #[TestDox('normalizes the raw resolutions and the merged report through the factory')]
    public function testNormalizesRawResolutionsAndReport(): void
    {
        $response = DiagnoseResponse::fromReport(
            ['el-1' => [new PropertyResolution('headline', PropertyKind::Primitive, false, 'string', 'hi')]],
            new DiagnosticsReport([new Violation(ViolationCode::DuplicateElementId, 'el-1', null, 'dup')]),
            [],
        );

        $decoded = json_decode((string) json_encode($response, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('headline', $decoded['resolutions']['el-1'][0]['key']);
        static::assertFalse($decoded['diagnostics']['wellFormed']);
        static::assertSame(ViolationCode::DuplicateElementId->value, $decoded['diagnostics']['violations'][0]['code']);
    }

    #[TestDox('serializes a non-empty applicableBindings map as an object of id-keyed lists, never {} per element')]
    public function testSerializesNonEmptyApplicableBindings(): void
    {
        $response = DiagnoseResponse::fromReport(
            [],
            new DiagnosticsReport([]),
            ['el-1' => ['core:from-media-library'], 'el-2' => []],
        );

        $json = (string) json_encode($response, \JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(['el-1', 'el-2'], array_keys($decoded['applicableBindings']));
        static::assertSame(['core:from-media-library'], $decoded['applicableBindings']['el-1']);
        static::assertSame([], $decoded['applicableBindings']['el-2']);
        // the outer map is a JSON object, but each empty inner value stays a JSON array, never "{}"
        static::assertStringContainsString('"applicableBindings":{"el-1":["core:from-media-library"],"el-2":[]}', $json);
    }

    #[TestDox('serializes empty resolutions and applicable bindings as JSON objects exposing exactly the three wire keys without leaking apiAlias or extensions')]
    public function testSerializesEmptyResolutionsWithExactlyTheThreeWireKeys(): void
    {
        $response = DiagnoseResponse::fromReport([], new DiagnosticsReport([]), []);

        $json = (string) json_encode($response, \JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertStringContainsString('"resolutions":{}', $json);
        static::assertStringContainsString('"applicableBindings":{}', $json);
        static::assertSame(['resolutions', 'diagnostics', 'applicableBindings'], array_keys($decoded));
        static::assertArrayNotHasKey('apiAlias', $decoded);
        static::assertArrayNotHasKey('extensions', $decoded);
    }
}
