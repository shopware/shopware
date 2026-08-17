<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DraftLayoutDecoder::class)]
class DraftLayoutDecoderTest extends TestCase
{
    #[TestDox('decode returns the decoded element tree for a structurally valid layout')]
    public function testDecodeReturnsTree(): void
    {
        $element = new ContentElement('el-1', 'Sw:Block');
        $decoder = $this->decoder($this->serializerDecoding($element));

        $tree = $decoder->decode([['id' => 'el-1', 'component' => 'Sw:Block']]);

        static::assertEquals([$element], $tree);
    }

    #[TestDox('decodeOne returns the single decoded element for a structurally valid element')]
    public function testDecodeOneReturnsElement(): void
    {
        $element = new ContentElement('el-1', 'Sw:Block');
        $decoder = $this->decoder($this->serializerDecoding($element));

        static::assertEquals($element, $decoder->decodeOne(['id' => 'el-1', 'component' => 'Sw:Block']));
    }

    #[TestDox('decodeOne throws invalidLayoutStructure for a malformed element instead of a serializer error')]
    public function testDecodeOneRejectsMalformedElement(): void
    {
        $decoder = $this->decoder(static::createStub(ContentElementFieldSerializer::class));

        try {
            $decoder->decodeOne(['component' => 'Sw:Block']);
            static::fail('Expected a ContentSystemException for the malformed element.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
        }
    }

    /**
     * @param array<int|string, mixed> $rawLayout
     */
    #[DataProvider('invalidLayoutProvider')]
    #[TestDox('decode rejects a structurally invalid layout with a precise violation list')]
    public function testDecodeRejectsInvalidLayout(array $rawLayout, ConstraintViolationList $expectedViolations): void
    {
        $decoder = $this->decoder(static::createStub(ContentElementFieldSerializer::class));

        $this->expectExceptionObject(ContentSystemException::invalidLayoutStructure($expectedViolations));

        $decoder->decode($rawLayout);
    }

    /**
     * @return iterable<string, array{array<int|string, mixed>, ConstraintViolationList}>
     */
    public static function invalidLayoutProvider(): iterable
    {
        yield 'non-array top-level element' => [
            ['not-an-array'],
            new ConstraintViolationList([
                new ConstraintViolation('Layout element must be an array.', null, [], null, '[0]', 'not-an-array'),
            ]),
        ];

        yield 'element missing both id and component aggregates both violations' => [
            [[]],
            new ConstraintViolationList([
                new ConstraintViolation('Layout element id must be a non-empty string.', null, [], null, '[0].id', null),
                new ConstraintViolation('Layout element component must be a non-empty string.', null, [], null, '[0].component', null),
            ]),
        ];

        yield 'duplicate element id rejected before any mutation runs' => [
            [
                ['id' => 'dup', 'component' => 'Sw:Block'],
                ['id' => 'dup', 'component' => 'Sw:Other'],
            ],
            new ConstraintViolationList([
                new ConstraintViolation('Layout element id "dup" is not unique across the layout.', null, [], null, '[1].id', 'dup'),
            ]),
        ];

        yield 'non-array nested child rejected instead of silently dropped' => [
            [[
                'id' => 'root',
                'component' => 'Sw:Block',
                'slots' => ['content' => ['not-an-array']],
            ]],
            new ConstraintViolationList([
                new ConstraintViolation('Layout element must be an array.', null, [], null, '[0].slots.content[0]', 'not-an-array'),
            ]),
        ];

        yield 'non-array slot children container rejected instead of silently dropped' => [
            [[
                'id' => 'root',
                'component' => 'Sw:Block',
                'slots' => ['main' => 'garbage'],
            ]],
            new ConstraintViolationList([
                new ConstraintViolation('Layout slot must be an array of elements.', null, [], null, '[0].slots.main', 'garbage'),
            ]),
        ];

        yield 'element missing only the id' => [
            [['component' => 'Sw:Block']],
            new ConstraintViolationList([
                new ConstraintViolation('Layout element id must be a non-empty string.', null, [], null, '[0].id', null),
            ]),
        ];

        yield 'element missing only the component' => [
            [['id' => 'el-1']],
            new ConstraintViolationList([
                new ConstraintViolation('Layout element component must be a non-empty string.', null, [], null, '[0].component', null),
            ]),
        ];
    }

    #[TestDox('decode rejects a tree nested past the maximum depth')]
    public function testDecodeRejectsExcessiveNestingDepth(): void
    {
        $decoder = $this->decoder(static::createStub(ContentElementFieldSerializer::class));

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

    #[TestDox('decode aggregates a client-defect decode failure into a 400 invalidLayoutStructure')]
    public function testDecodeRewrapsClientDefect(): void
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willThrowException(ContentSystemException::unknownLoaderEntity('prodct'));

        $decoder = $this->decoder($serializer);

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

        $decoder = $this->decoder($serializer);

        $this->expectExceptionObject(ContentSystemException::layoutNotFound('x'));
        $decoder->decode([['id' => 'el-1', 'component' => 'Sw:Block']]);
    }

    #[TestDox('decodeLintable returns the decoded tree and no violations for a valid layout')]
    public function testDecodeLintableReturnsTreeWithoutViolations(): void
    {
        $element = new ContentElement('el-1', 'Sw:Block');
        $decoder = $this->decoder($this->serializerDecoding($element));

        [$tree, $violations] = $decoder->decodeLintable([['id' => 'el-1', 'component' => 'Sw:Block']]);

        static::assertEquals([$element], $tree);
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

        $decoder = $this->decoder($serializer);

        [$tree, $violations] = $decoder->decodeLintable([
            ['id' => 'bad', 'component' => 'Sw:Block'],
            ['id' => 'good', 'component' => 'Sw:Block'],
        ]);

        static::assertEquals([$good], $tree);
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

        $decoder = $this->decoder($serializer);

        [$tree, $violations] = $decoder->decodeLintable([
            ['id' => 'dup', 'component' => 'Sw:Block'],
            ['id' => 'dup', 'component' => 'Sw:Other'],
        ]);

        static::assertEquals([$first, $second], $tree);
        static::assertSame([], $violations);
    }

    #[TestDox('decodeLintable still throws invalidLayoutStructure for a structurally invalid element')]
    public function testDecodeLintableRejectsStructurallyInvalidElement(): void
    {
        $decoder = $this->decoder(static::createStub(ContentElementFieldSerializer::class));

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

        $decoder = $this->decoder($serializer);

        $this->expectExceptionObject(ContentSystemException::layoutNotFound('x'));
        $decoder->decodeLintable([['id' => 'el-1', 'component' => 'Sw:Block']]);
    }

    #[TestDox('decode canonicalises the style of a decoded element through the style normalizer')]
    public function testDecodeNormalizesElementStyle(): void
    {
        $element = new ContentElement(
            'el-1',
            'Sw:Block',
            style: new ElementStyle(['align-self' => ['xs' => 'center']]),
        );

        $tree = $this->decoder($this->serializerDecoding($element))->decode([['id' => 'el-1', 'component' => 'Sw:Block']]);

        static::assertSame(
            ['align-self' => ['xs' => 'center', 'sm' => 'auto', 'md' => 'auto', 'lg' => 'auto', 'xl' => 'auto', 'xxl' => 'auto']],
            $tree[0]->getStyle()->toArray(),
        );
    }

    #[TestDox('decode canonicalises the style of a slot child, not only of the root element')]
    public function testDecodeNormalizesSlotChildStyle(): void
    {
        $child = new ContentElement(
            'child',
            'Sw:Text',
            style: new ElementStyle(['align-self' => ['xs' => 'end']]),
        );
        $root = new ContentElement(
            'root',
            'Sw:Block',
            slots: ['content' => new SlotContent([$child])],
        );

        $tree = $this->decoder($this->serializerDecoding($root))->decode([['id' => 'root', 'component' => 'Sw:Block']]);

        $decodedChild = $tree[0]->getSlots()['content']->first();
        static::assertInstanceOf(ContentElement::class, $decodedChild);
        static::assertSame(
            ['align-self' => ['xs' => 'end', 'sm' => 'auto', 'md' => 'auto', 'lg' => 'auto', 'xl' => 'auto', 'xxl' => 'auto']],
            $decodedChild->getStyle()->toArray(),
        );
    }

    #[TestDox('decodeLintable canonicalises style too, so the diagnose route sees the saved shape')]
    public function testDecodeLintableNormalizesElementStyle(): void
    {
        $element = new ContentElement(
            'el-1',
            'Sw:Block',
            style: new ElementStyle(['align-self' => ['xs' => 'center']]),
        );

        [$tree, $violations] = $this->decoder($this->serializerDecoding($element))->decodeLintable([['id' => 'el-1', 'component' => 'Sw:Block']]);

        static::assertSame([], $violations);
        static::assertSame(
            ['align-self' => ['xs' => 'center', 'sm' => 'auto', 'md' => 'auto', 'lg' => 'auto', 'xl' => 'auto', 'xxl' => 'auto']],
            $tree[0]->getStyle()->toArray(),
        );
    }

    private function serializerDecoding(ContentElement $element): ContentElementFieldSerializer
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willReturn($element);

        return $serializer;
    }

    private function decoder(ContentElementFieldSerializer $serializer): DraftLayoutDecoder
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([
            'align-self' => new StyleOptionSpecification(
                'align-self',
                new StyleOptionValueType('string', ['auto', 'start', 'center', 'end'], null, null, 'auto'),
                true,
                null,
                'core',
            ),
        ]);

        return new DraftLayoutDecoder($serializer, new ElementStyleNormalizer($registry, new BoxSpacingNormalizer()));
    }
}
