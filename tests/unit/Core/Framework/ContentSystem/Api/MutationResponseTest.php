<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\MutationResponse;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Mutation\MutationResult;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;

/**
 * @internal
 */
#[CoversClass(MutationResponse::class)]
class MutationResponseTest extends TestCase
{
    #[TestDox('encodes the empty map fields as JSON objects, not arrays')]
    public function testEmptyMapFieldsEncodeAsJsonObjects(): void
    {
        $response = MutationResponse::fromResult(
            new MutationResult([], [], new DiagnosticsReport([]), []),
            $this->elementSerializer(),
        );

        $json = json_encode($response, \JSON_THROW_ON_ERROR);

        static::assertStringContainsString('"resolutions":{}', $json);
        static::assertStringContainsString('"droppedProperties":{}', $json);
    }

    #[TestDox('encodes the empty list fields as JSON arrays, not objects')]
    public function testEmptyListFieldsEncodeAsJsonArrays(): void
    {
        $response = MutationResponse::fromResult(
            new MutationResult([], [], new DiagnosticsReport([]), []),
            $this->elementSerializer(),
        );

        $json = json_encode($response, \JSON_THROW_ON_ERROR);

        static::assertStringContainsString('"layout":[]', $json);
        static::assertStringContainsString('"affectedElementIds":[]', $json);
        static::assertStringContainsString('"orphaned":[]', $json);
        static::assertStringContainsString('"droppedWiring":[]', $json);
    }

    #[TestDox('serializes exactly the seven wire keys without leaking apiAlias or extensions')]
    public function testSerializesExactlyTheSevenWireKeys(): void
    {
        $response = MutationResponse::fromResult(
            new MutationResult([], [], new DiagnosticsReport([]), []),
            $this->elementSerializer(),
        );

        $decoded = json_decode((string) json_encode($response, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            ['layout', 'resolutions', 'diagnostics', 'affectedElementIds', 'orphaned', 'droppedWiring', 'droppedProperties'],
            array_keys($decoded),
        );
        static::assertArrayNotHasKey('apiAlias', $decoded);
        static::assertArrayNotHasKey('extensions', $decoded);
    }

    #[TestDox('maps every MutationResult field through to its serialized wire counterpart')]
    public function testMapsEveryResultFieldThrough(): void
    {
        $result = new MutationResult(
            [new ContentElement('el-1', 'Sw:Card')],
            ['el-1' => [new PropertyResolution('headline', PropertyKind::Primitive, false, 'string', 'hi')]],
            new DiagnosticsReport([]),
            ['el-1'],
            [new ContentElement('orphan', 'Sw:Block')],
            ['legacy'],
            ['headline' => 'Old headline'],
        );

        $decoded = json_decode(
            (string) json_encode(MutationResponse::fromResult($result, $this->elementSerializer()), \JSON_THROW_ON_ERROR),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        static::assertSame('el-1', $decoded['layout'][0]['id']);
        static::assertSame('headline', $decoded['resolutions']['el-1'][0]['key']);
        static::assertTrue($decoded['diagnostics']['wellFormed']);
        static::assertSame(['el-1'], $decoded['affectedElementIds']);
        static::assertSame('orphan', $decoded['orphaned'][0]['id']);
        static::assertSame(['legacy'], $decoded['droppedWiring']);
        static::assertSame('Old headline', $decoded['droppedProperties']['headline']);
    }

    private function elementSerializer(): ContentElementFieldSerializer
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('serializeContentElement')->willReturnCallback(
            static fn (ContentElement $element): array => ['id' => $element->getId(), 'component' => $element->getComponent(), 'properties' => []],
        );

        return $serializer;
    }
}
