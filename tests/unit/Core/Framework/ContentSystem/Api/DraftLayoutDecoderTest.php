<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(DraftLayoutDecoder::class)]
class DraftLayoutDecoderTest extends TestCase
{
    #[TestDox('decode returns the decoded element tree for a structurally valid layout')]
    public function testDecodeReturnsTree(): void
    {
        $element = new ContentElement('el-1', 'Sw:Block');
        $decoder = new DraftLayoutDecoder($this->serializerDecoding($element));

        $tree = $decoder->decode([['id' => 'el-1', 'component' => 'Sw:Block']]);

        static::assertSame([$element], $tree);
    }

    #[TestDox('decodeOne returns the single decoded element for a structurally valid element')]
    public function testDecodeOneReturnsElement(): void
    {
        $element = new ContentElement('el-1', 'Sw:Block');
        $decoder = new DraftLayoutDecoder($this->serializerDecoding($element));

        static::assertSame($element, $decoder->decodeOne(['id' => 'el-1', 'component' => 'Sw:Block']));
    }

    #[TestDox('decodeOne throws invalidLayoutStructure for a malformed element instead of a serializer error')]
    public function testDecodeOneRejectsMalformedElement(): void
    {
        $decoder = new DraftLayoutDecoder(static::createStub(ContentElementFieldSerializer::class));

        try {
            $decoder->decodeOne(['component' => 'Sw:Block']);
            static::fail('Expected a ContentSystemException for the malformed element.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
        }
    }

    #[TestDox('decode rejects a non-array top-level element with a precise structural violation')]
    public function testDecodeRejectsNonArrayElement(): void
    {
        $decoder = new DraftLayoutDecoder(static::createStub(ContentElementFieldSerializer::class));

        $this->expectExceptionObject(ContentSystemException::invalidLayoutStructure(
            new ConstraintViolationList([
                new ConstraintViolation('Layout element must be an array.', null, [], null, '[0]', 'not-an-array'),
            ]),
        ));

        $decoder->decode(['not-an-array']);
    }

    #[TestDox('decode aggregates both the id and component violations when an element is missing both')]
    public function testDecodeRejectsElementMissingBothIdAndComponent(): void
    {
        $decoder = new DraftLayoutDecoder(static::createStub(ContentElementFieldSerializer::class));

        $this->expectExceptionObject(ContentSystemException::invalidLayoutStructure(
            new ConstraintViolationList([
                new ConstraintViolation('Layout element id must be a non-empty string.', null, [], null, '[0].id', null),
                new ConstraintViolation('Layout element component must be a non-empty string.', null, [], null, '[0].component', null),
            ]),
        ));

        $decoder->decode([[]]);
    }

    #[TestDox('decode rejects a duplicate element id before any mutation runs')]
    public function testDecodeRejectsDuplicateElementId(): void
    {
        $decoder = new DraftLayoutDecoder(static::createStub(ContentElementFieldSerializer::class));

        $this->expectExceptionObject(ContentSystemException::invalidLayoutStructure(
            new ConstraintViolationList([
                new ConstraintViolation('Layout element id "dup" is not unique across the layout.', null, [], null, '[1].id', 'dup'),
            ]),
        ));

        $decoder->decode([
            ['id' => 'dup', 'component' => 'Sw:Block'],
            ['id' => 'dup', 'component' => 'Sw:Other'],
        ]);
    }

    #[TestDox('decode rejects a nested child that is not an array instead of letting it be silently dropped')]
    public function testDecodeRejectsNonArrayNestedChild(): void
    {
        $decoder = new DraftLayoutDecoder(static::createStub(ContentElementFieldSerializer::class));

        $this->expectExceptionObject(ContentSystemException::invalidLayoutStructure(
            new ConstraintViolationList([
                new ConstraintViolation('Layout element must be an array.', null, [], null, '[0].slots.content[0]', 'not-an-array'),
            ]),
        ));

        $decoder->decode([[
            'id' => 'root',
            'component' => 'Sw:Block',
            'slots' => ['content' => ['not-an-array']],
        ]]);
    }

    #[TestDox('decode rejects a tree nested past the maximum depth')]
    public function testDecodeRejectsExcessiveNestingDepth(): void
    {
        $decoder = new DraftLayoutDecoder(static::createStub(ContentElementFieldSerializer::class));

        $element = ['id' => 'leaf', 'component' => 'Sw:Block'];
        for ($level = 0; $level < 60; ++$level) {
            $element = ['id' => 'n' . $level, 'component' => 'Sw:Block', 'slots' => ['content' => [$element]]];
        }

        try {
            $decoder->decode([$element]);
            static::fail('Expected a ContentSystemException for the over-deep tree.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertStringContainsString('maximum depth', $exception->getMessage());
        }
    }

    #[TestDox('decode throws a per-field invalidLayoutStructure violation for an element missing a non-empty string id')]
    public function testDecodeRejectsMissingId(): void
    {
        $decoder = new DraftLayoutDecoder(static::createStub(ContentElementFieldSerializer::class));

        $this->expectExceptionObject(ContentSystemException::invalidLayoutStructure(
            new ConstraintViolationList([
                new ConstraintViolation('Layout element id must be a non-empty string.', null, [], null, '[0].id', null),
            ]),
        ));

        $decoder->decode([['component' => 'Sw:Block']]);
    }

    #[TestDox('decode throws a per-field invalidLayoutStructure violation for an element missing a non-empty string component')]
    public function testDecodeRejectsMissingComponent(): void
    {
        $decoder = new DraftLayoutDecoder(static::createStub(ContentElementFieldSerializer::class));

        $this->expectExceptionObject(ContentSystemException::invalidLayoutStructure(
            new ConstraintViolationList([
                new ConstraintViolation('Layout element component must be a non-empty string.', null, [], null, '[0].component', null),
            ]),
        ));

        $decoder->decode([['id' => 'el-1']]);
    }

    #[TestDox('decode aggregates a client-defect decode failure into a 400 invalidLayoutStructure')]
    public function testDecodeRewrapsClientDefect(): void
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willThrowException(ContentSystemException::unknownLoaderEntity('prodct'));

        $decoder = new DraftLayoutDecoder($serializer);

        try {
            $decoder->decode([['id' => 'el-1', 'component' => 'Sw:Block']]);
            static::fail('Expected a ContentSystemException for the client-defect decode failure.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
        }
    }

    #[TestDox('decode rethrows a non-client-defect decode fault unchanged')]
    public function testDecodeRethrowsInternalFault(): void
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willThrowException(ContentSystemException::layoutNotFound('x'));

        $decoder = new DraftLayoutDecoder($serializer);

        $this->expectExceptionObject(ContentSystemException::layoutNotFound('x'));
        $decoder->decode([['id' => 'el-1', 'component' => 'Sw:Block']]);
    }

    #[TestDox('decodeLintable returns the decoded tree and no violations for a valid layout')]
    public function testDecodeLintableReturnsTreeWithoutViolations(): void
    {
        $element = new ContentElement('el-1', 'Sw:Block');
        $decoder = new DraftLayoutDecoder($this->serializerDecoding($element));

        [$tree, $violations] = $decoder->decodeLintable([['id' => 'el-1', 'component' => 'Sw:Block']]);

        static::assertSame([$element], $tree);
        static::assertSame([], $violations);
    }

    #[TestDox('decodeLintable collects a client-defect as an invalid_config violation and keeps the rest of the tree')]
    public function testDecodeLintableCollectsClientDefect(): void
    {
        $good = new ContentElement('good', 'Sw:Block');

        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willReturnCallback(
            static fn (array $data): ContentElement => $data['id'] === 'bad'
                ? throw ContentSystemException::unknownLoaderEntity('prodct')
                : $good,
        );

        $decoder = new DraftLayoutDecoder($serializer);

        [$tree, $violations] = $decoder->decodeLintable([
            ['id' => 'bad', 'component' => 'Sw:Block'],
            ['id' => 'good', 'component' => 'Sw:Block'],
        ]);

        static::assertSame([$good], $tree);
        static::assertCount(1, $violations);
        static::assertSame(ViolationCode::InvalidConfig, $violations[0]->code);
        static::assertSame('bad', $violations[0]->elementId);
    }

    #[TestDox('decodeLintable keeps a duplicate-id tree so the diagnostics pass can report it, instead of rejecting')]
    public function testDecodeLintableKeepsDuplicateIdTreeForDiagnostics(): void
    {
        $first = new ContentElement('dup', 'Sw:Block');
        $second = new ContentElement('dup', 'Sw:Other');

        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willReturnOnConsecutiveCalls($first, $second);

        $decoder = new DraftLayoutDecoder($serializer);

        [$tree, $violations] = $decoder->decodeLintable([
            ['id' => 'dup', 'component' => 'Sw:Block'],
            ['id' => 'dup', 'component' => 'Sw:Other'],
        ]);

        static::assertSame([$first, $second], $tree);
        static::assertSame([], $violations);
    }

    #[TestDox('decodeLintable still throws invalidLayoutStructure for a structurally invalid element')]
    public function testDecodeLintableRejectsStructurallyInvalidElement(): void
    {
        $decoder = new DraftLayoutDecoder(static::createStub(ContentElementFieldSerializer::class));

        try {
            $decoder->decodeLintable([['component' => 'Sw:Block']]);
            static::fail('Expected a ContentSystemException for the structurally invalid element.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
        }
    }

    #[TestDox('decodeLintable rethrows a non-client-defect decode fault unchanged')]
    public function testDecodeLintableRethrowsInternalFault(): void
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willThrowException(ContentSystemException::layoutNotFound('x'));

        $decoder = new DraftLayoutDecoder($serializer);

        $this->expectExceptionObject(ContentSystemException::layoutNotFound('x'));
        $decoder->decodeLintable([['id' => 'el-1', 'component' => 'Sw:Block']]);
    }

    private function serializerDecoding(ContentElement $element): ContentElementFieldSerializer
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willReturn($element);

        return $serializer;
    }
}
