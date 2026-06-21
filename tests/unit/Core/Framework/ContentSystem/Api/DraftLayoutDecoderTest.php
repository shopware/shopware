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

    #[TestDox('decode throws invalidLayoutStructure for an element that is not an array')]
    public function testDecodeRejectsNonArrayElement(): void
    {
        $decoder = new DraftLayoutDecoder(static::createStub(ContentElementFieldSerializer::class));

        try {
            $decoder->decode(['not-an-array']);
            static::fail('Expected a ContentSystemException for the non-array element.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
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
