<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Encoder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Encoder\ResolvedValueIndexEncoder;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndex;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResolvedValueIndexEncoder::class)]
class ResolvedValueIndexEncoderTest extends TestCase
{
    #[TestDox('hands every struct-valued index entry to the framework encoder, which is what applies the protection gate')]
    public function testEncodeDelegatesStructValuesToTheStructEncoder(): void
    {
        $payload = new StubStruct();

        $structEncoder = static::createMock(StructEncoder::class);
        $structEncoder->expects($this->once())
            ->method('encode')
            ->with(static::identicalTo($payload))
            ->willReturn(['protection' => 'applied']);

        $index = new ResolvedValueIndex(['r1' => $payload], []);

        $body = (new ResolvedValueIndexEncoder($structEncoder))->encode($this->renderResult($index));

        static::assertSame(['protection' => 'applied'], $body['data']['r1']);
    }

    #[TestDox('reaches a struct nested two levels deep inside an index value')]
    public function testEncodeDescendsIntoArrayValues(): void
    {
        $payload = new StubStruct();

        $structEncoder = static::createMock(StructEncoder::class);
        $structEncoder->expects($this->once())
            ->method('encode')
            ->with(static::identicalTo($payload))
            ->willReturn(['encoded' => true]);

        $index = new ResolvedValueIndex(['r1' => ['gallery' => ['first' => $payload, 'caption' => 'Alpha']]], []);

        $body = (new ResolvedValueIndexEncoder($structEncoder))->encode($this->renderResult($index));

        static::assertSame(
            ['gallery' => ['first' => ['encoded' => true], 'caption' => 'Alpha']],
            $body['data']['r1']
        );
    }

    #[TestDox('passes a non-struct object through untouched, because only structs carry the protection gate')]
    public function testEncodeKeepsANonStructObjectUntouched(): void
    {
        $object = new \stdClass();
        $object->headline = 'Alpha';

        $structEncoder = static::createMock(StructEncoder::class);
        $structEncoder->expects($this->never())->method('encode');

        $index = new ResolvedValueIndex(['r1' => $object], []);

        $body = (new ResolvedValueIndexEncoder($structEncoder))->encode($this->renderResult($index));

        static::assertSame($object, $body['data']['r1']);
    }

    /**
     * Three refs in an order no sort would produce, so the assertion fails if the encoder rebuilds the data map
     * rather than mapping over it in place.
     */
    #[TestDox('passes scalar values through untouched and keeps the index ref order')]
    public function testEncodeKeepsScalarValuesAndTheirRefOrder(): void
    {
        $index = new ResolvedValueIndex(['r3' => 'Gamma', 'r1' => 24, 'r2' => null], []);

        $body = $this->encode($index);

        static::assertSame(['r3' => 'Gamma', 'r1' => 24, 'r2' => null], $body['data']);
        static::assertSame(['r3', 'r1', 'r2'], array_keys($body['data']));
    }

    /**
     * Element ids and property keys both descend rather than ascend, so an encoder that sorted either level
     * would fail here.
     */
    #[TestDox('hands the assignment map through unchanged, including the order of its element ids and keys')]
    public function testEncodeHandsTheAssignmentMapThroughUnchanged(): void
    {
        $assignments = [
            'zulu-element' => ['title' => 'r2', 'anchor' => 'r1'],
            'alpha-element' => ['title' => 'r1'],
        ];
        $index = new ResolvedValueIndex(['r1' => 'Alpha', 'r2' => 'Beta'], $assignments);

        $body = $this->encode($index);

        static::assertSame($assignments, $body['assignments']);
    }

    /**
     * The ref is read back out of the emitted assignments rather than written into the assertion, so nothing
     * here pins a literal ref id. Two data entries, so resolving the ref selects one of them.
     */
    #[TestDox('keeps every ref an assignment names resolvable in the data map it emits')]
    public function testEncodeKeepsAssignedRefsResolvableInTheEmittedData(): void
    {
        $index = new ResolvedValueIndex(['r2' => 'Beta', 'r1' => 'Alpha'], ['zulu-element' => ['anchor' => 'r2']]);

        $body = $this->encode($index);

        $ref = $body['assignments']['zulu-element']['anchor'];

        static::assertArrayHasKey($ref, $body['data']);
        static::assertSame('Beta', $body['data'][$ref]);
    }

    #[TestDox('emits both maps as empty arrays for an index holding nothing')]
    public function testEncodeEmitsEmptyMapsAsArrays(): void
    {
        $body = $this->encode(new ResolvedValueIndex([], []));

        static::assertSame(['data' => [], 'assignments' => []], $body);
    }

    #[TestDox('fails hard on a render result carrying no index rather than serving an empty data map for it')]
    public function testEncodeThrowsWhenTheRenderResultCarriesNoIndex(): void
    {
        $encoder = new ResolvedValueIndexEncoder(static::createStub(StructEncoder::class));

        try {
            $encoder->encode($this->renderResult(null));
            static::fail('Expected ContentSystemException was not thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::RESOLVED_VALUE_INDEX_MISSING, $exception->getErrorCode());
            static::assertStringContainsString('layout-1', $exception->getMessage());
        }
    }

    /**
     * @return array{data: array<string, mixed>, assignments: array<string, array<string, string>>}
     */
    private function encode(ResolvedValueIndex $index): array
    {
        $encoder = new ResolvedValueIndexEncoder(static::createStub(StructEncoder::class));

        return $encoder->encode($this->renderResult($index));
    }

    private function renderResult(?ResolvedValueIndex $index): RenderResult
    {
        return new RenderResult(
            [],
            LayoutReference::create('layout-1', 'Landing', '1.0.0'),
            $index,
        );
    }
}
