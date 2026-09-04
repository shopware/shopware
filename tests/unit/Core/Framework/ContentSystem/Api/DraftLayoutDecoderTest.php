<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DraftLayoutDecoder::class)]
class DraftLayoutDecoderTest extends TestCase
{
    #[TestDox('decode returns the decoded element tree with properties wrapped into the storage value envelope')]
    public function testDecodeReturnsTreeWithPropertiesWrapped(): void
    {
        $tree = $this->decoder()->decode([['id' => 'el-1', 'component' => 'Sw:Block', 'properties' => ['headline' => 'Hi']]]);

        static::assertCount(1, $tree);
        static::assertSame('el-1', $tree[0]->id);
        static::assertSame('Sw:Block', $tree[0]->component);
        static::assertSame('Hi', $tree[0]->property('headline')?->jsonSerialize());
    }

    #[TestDox('decodeOne returns the single decoded element for a structurally valid element')]
    public function testDecodeOneReturnsElement(): void
    {
        $element = $this->decoder()->decodeOne(['id' => 'el-1', 'component' => 'Sw:Block']);

        static::assertSame('el-1', $element->id);
    }

    #[TestDox('decodeLintable returns the decoded tree and no violations for a valid layout')]
    public function testDecodeLintableReturnsTreeWithoutViolations(): void
    {
        [$tree, $violations] = $this->decoder()->decodeLintable([['id' => 'el-1', 'component' => 'Sw:Block']]);

        static::assertSame(['el-1'], array_map(static fn (StoredElement $e): string => $e->id, $tree));
        static::assertSame([], $violations);
    }

    #[TestDox('decode canonicalises the style of a decoded element through the style normalizer')]
    public function testDecodeNormalizesElementStyle(): void
    {
        $tree = $this->decoder()->decode([[
            'id' => 'el-1',
            'component' => 'Sw:Block',
            'style' => ['align-self' => ['xs' => 'center']],
        ]]);

        static::assertSame(
            ['align-self' => ['xs' => 'center', 'sm' => 'auto', 'md' => 'auto', 'lg' => 'auto', 'xl' => 'auto', 'xxl' => 'auto']],
            $tree[0]->style->toArray(),
        );
    }

    #[TestDox('decode canonicalises the style of a slot child, not only of the root element')]
    public function testDecodeNormalizesSlotChildStyle(): void
    {
        $tree = $this->decoder()->decode([[
            'id' => 'root',
            'component' => 'Sw:Block',
            'slots' => ['content' => [[
                'id' => 'child',
                'component' => 'Sw:Text',
                'style' => ['align-self' => ['xs' => 'end']],
            ]]],
        ]]);

        static::assertSame(
            ['align-self' => ['xs' => 'end', 'sm' => 'auto', 'md' => 'auto', 'lg' => 'auto', 'xl' => 'auto', 'xxl' => 'auto']],
            $tree[0]->slots['content'][0]->style->toArray(),
        );
    }

    #[TestDox('decodeLintable canonicalises style too, so the diagnose route sees the saved shape')]
    public function testDecodeLintableNormalizesElementStyle(): void
    {
        [$tree, $violations] = $this->decoder()->decodeLintable([[
            'id' => 'el-1',
            'component' => 'Sw:Block',
            'style' => ['align-self' => ['xs' => 'center']],
        ]]);

        static::assertSame([], $violations);
        static::assertSame(
            ['align-self' => ['xs' => 'center', 'sm' => 'auto', 'md' => 'auto', 'lg' => 'auto', 'xl' => 'auto', 'xxl' => 'auto']],
            $tree[0]->style->toArray(),
        );
    }

    #[TestDox('decodeLintable keeps a duplicate-id tree so the diagnostics pass can report it, instead of rejecting')]
    public function testDecodeLintableKeepsDuplicateIdTreeForDiagnostics(): void
    {
        [$tree, $violations] = $this->decoder()->decodeLintable([
            ['id' => 'dup', 'component' => 'Sw:Block'],
            ['id' => 'dup', 'component' => 'Sw:Other'],
        ]);

        static::assertSame(['dup', 'dup'], array_map(static fn (StoredElement $e): string => $e->id, $tree));
        static::assertSame([], $violations);
    }

    #[TestDox('decodeLintable collects a client-defect as an invalid_config violation and keeps the rest of the tree')]
    public function testDecodeLintableCollectsClientDefect(): void
    {
        $decoder = $this->decoder($this->configProviderThrowing(ContentSystemException::unknownLoaderEntity('prodct')));

        [$tree, $violations] = $decoder->decodeLintable([
            $this->elementWithDataRequirement('bad'),
            ['id' => 'good', 'component' => 'Sw:Block'],
        ]);

        static::assertCount(1, $tree);
        static::assertSame('good', $tree[0]->id);
        static::assertCount(1, $violations);
        static::assertSame(ViolationCode::InvalidConfig, $violations[0]->code);
        static::assertSame('bad', $violations[0]->elementId);
    }

    /**
     * @param array<string, mixed> $wiring
     */
    #[DataProvider('elementLocalWiringDefectProvider')]
    #[TestDox('decodeLintable reports $_dataName as an invalid_config violation on its element')]
    public function testDecodeLintableCollectsAnElementLocalWiringDefect(array $wiring, string $expectedMessageFragment): void
    {
        [$tree, $violations] = $this->decoder()->decodeLintable([
            ['id' => 'defective', 'component' => 'Sw:Block', ...$wiring],
            ['id' => 'good', 'component' => 'Sw:Block'],
        ]);

        static::assertSame(['good'], array_map(static fn (StoredElement $e): string => $e->id, $tree));
        static::assertCount(1, $violations);
        static::assertSame(ViolationCode::InvalidConfig, $violations[0]->code);
        static::assertSame('defective', $violations[0]->elementId);
        static::assertStringContainsString($expectedMessageFragment, $violations[0]->message);
    }

    #[TestDox('decode rejects a tree nested past the codec maximum depth')]
    public function testDecodeRejectsExcessiveNestingDepth(): void
    {
        $element = ['id' => 'leaf', 'component' => 'Sw:Block'];
        for ($level = 0; $level < 60; ++$level) {
            $element = ['id' => 'n' . $level, 'component' => 'Sw:Block', 'slots' => ['content' => [$element]]];
        }

        try {
            $this->decoder()->decode([$element]);
            static::fail('Expected a ContentSystemException for the over-deep tree.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertStringContainsString('element nesting at most ' . StoredElementCodec::MAX_NESTING_DEPTH . ' levels deep', $exception->getMessage());
        }
    }

    #[TestDox('decodeOne throws invalidLayoutStructure for a malformed element instead of a codec error')]
    public function testDecodeOneRejectsMalformedElement(): void
    {
        try {
            $this->decoder()->decodeOne(['component' => 'Sw:Block']);
            static::fail('Expected a ContentSystemException for the malformed element.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertStringContainsString('Layout element id must be a non-empty string.', $exception->getMessage());
        }
    }

    /**
     * @param array<int|string, mixed> $rawLayout
     */
    #[DataProvider('invalidLayoutProvider')]
    #[TestDox('decode rejects a structurally invalid layout with a precise violation list')]
    public function testDecodeRejectsInvalidLayout(array $rawLayout, ConstraintViolationList $expectedViolations): void
    {
        $this->expectExceptionObject(ContentSystemException::invalidLayoutStructure($expectedViolations));

        $this->decoder()->decode($rawLayout);
    }

    #[TestDox('decode rejects a globally duplicate element id, reading the rule off the stored forest')]
    public function testDecodeRejectsDuplicateElementId(): void
    {
        try {
            $this->decoder()->decode([
                ['id' => 'dup', 'component' => 'Sw:Block'],
                ['id' => 'dup', 'component' => 'Sw:Other'],
            ]);
            static::fail('Expected a ContentSystemException for the duplicate element id.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertStringContainsString('Element id "dup" is not unique across the layout.', $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $malformedElement
     */
    #[DataProvider('malformedContainerProvider')]
    #[TestDox('decode refuses a malformed nested container instead of silently emptying it')]
    public function testDecodeRefusesMalformedContainer(array $malformedElement): void
    {
        try {
            $this->decoder()->decode([$malformedElement]);
            static::fail('Expected a ContentSystemException for the malformed container.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
        }
    }

    #[TestDox('decode aggregates a client-defect decode failure into a 400 invalidLayoutStructure')]
    public function testDecodeRewrapsClientDefect(): void
    {
        $decoder = $this->decoder($this->configProviderThrowing(ContentSystemException::unknownLoaderEntity('prodct')));

        try {
            $decoder->decode([$this->elementWithDataRequirement('el-1')]);
            static::fail('Expected a ContentSystemException for the client-defect decode failure.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
        }
    }

    #[TestDox('decode refuses a numeric wiring key as a client defect rather than letting it escape as an internal fault')]
    public function testDecodeRefusesNumericWiringKeyAsClientDefect(): void
    {
        try {
            $this->decoder()->decode([['id' => 'el-1', 'component' => 'Sw:Block', 'properties' => ['5' => 'x']]]);
            static::fail('Expected a ContentSystemException for the numeric property key.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        }
    }

    /**
     * The three element-local wiring codes are client-defect codes, so they take the two draft-route paths the
     * other codec defects take rather than escaping as a fault: aggregated into the strict 400 here, collected
     * as a per-element `invalid_config` violation by {@see testDecodeLintableCollectsAnElementLocalWiringDefect()}.
     *
     * @param array<string, mixed> $wiring
     */
    #[DataProvider('elementLocalWiringDefectProvider')]
    #[TestDox('decode rejects $_dataName as a 400 invalidLayoutStructure')]
    public function testDecodeRejectsAnElementLocalWiringDefect(array $wiring, string $expectedMessageFragment): void
    {
        try {
            $this->decoder()->decode([['id' => 'el-1', 'component' => 'Sw:Block', ...$wiring]]);
            static::fail('Expected a ContentSystemException for the element-local wiring defect.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertStringContainsString($expectedMessageFragment, $exception->getMessage());
        }
    }

    #[TestDox('decode rethrows a non-client-defect decode fault unchanged')]
    public function testDecodeRethrowsInternalFault(): void
    {
        $decoder = $this->decoder($this->configProviderThrowing(ContentSystemException::layoutNotFound('x')));

        $this->expectExceptionObject(ContentSystemException::layoutNotFound('x'));
        $decoder->decode([$this->elementWithDataRequirement('el-1')]);
    }

    #[TestDox('decodeLintable still throws invalidLayoutStructure for a structurally invalid element')]
    public function testDecodeLintableRejectsStructurallyInvalidElement(): void
    {
        try {
            $this->decoder()->decodeLintable([['component' => 'Sw:Block']]);
            static::fail('Expected a ContentSystemException for the structurally invalid element.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertStringContainsString('Layout element id must be a non-empty string.', $exception->getMessage());
        }
    }

    #[TestDox('decodeLintable rethrows a non-client-defect decode fault unchanged')]
    public function testDecodeLintableRethrowsInternalFault(): void
    {
        $decoder = $this->decoder($this->configProviderThrowing(ContentSystemException::layoutNotFound('x')));

        $this->expectExceptionObject(ContentSystemException::layoutNotFound('x'));
        $decoder->decodeLintable([$this->elementWithDataRequirement('el-1')]);
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

    /**
     * A scalar `style` is the case the older decode path emptied rather than refused, which took the element's
     * style out of reach of the unknown-style-option diagnostic entirely. It is listed here alongside the other
     * containers because the codec judges all of them by the same rule.
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedContainerProvider(): iterable
    {
        yield 'scalar style' => [['id' => 'root', 'component' => 'Sw:Block', 'style' => 'garbage']];
        yield 'scalar slots' => [['id' => 'root', 'component' => 'Sw:Block', 'slots' => 'garbage']];
        yield 'non-list slot children container' => [['id' => 'root', 'component' => 'Sw:Block', 'slots' => ['main' => 'garbage']]];
        yield 'non-array nested child' => [['id' => 'root', 'component' => 'Sw:Block', 'slots' => ['content' => ['not-an-array']]]];
        yield 'scalar dataRequirements' => [['id' => 'root', 'component' => 'Sw:Block', 'dataRequirements' => 'garbage']];
        yield 'scalar acceptsContext' => [['id' => 'root', 'component' => 'Sw:Block', 'acceptsContext' => 'garbage']];
        yield 'scalar attributedSpecifications' => [['id' => 'root', 'component' => 'Sw:Block', 'attributedSpecifications' => 'garbage']];
    }

    /**
     * Each fragment carries the interpolated context keys, not only the rule's placeholder-free prose tail. The
     * three rules all surface as the same error code, so the identifiers are what tells one apart from another:
     * a fragment stopping at the shared tail would pass while the wrong rule fired on the wrong key.
     *
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function elementLocalWiringDefectProvider(): iterable
    {
        yield 'two consumers sharing one base key' => [
            ['acceptsContext' => [
                'product' => ['type' => 'single', 'required' => true],
                'category' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'product'],
            ]],
            'Property key "product" is used by both context "product" and "category".',
        ];

        yield 'a redistributing consumer keyed by a dotted path' => [
            ['acceptsContext' => [
                'product.manufacturer' => ['type' => 'single', 'required' => true, 'redistribute' => true],
            ]],
            'Context key "product.manufacturer" uses dot notation and cannot be redistributed.',
        ];

        yield 'a redistributing consumer whose derived key an authored provider holds' => [
            [
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => ['product' => ['type' => 'single', 'required' => true, 'redistribute' => true]],
            ],
            'Context key "product" has both redistribute:true and explicit providesContext.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function elementWithDataRequirement(string $id): array
    {
        return [
            'id' => $id,
            'component' => 'Sw:Block',
            'dataRequirements' => ['product' => ['source' => 'entity', 'config' => ['entity' => 'prodct']]],
        ];
    }

    private function configProviderThrowing(ContentSystemException $exception): DataLoaderConfigSerializerProvider
    {
        $provider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $provider->method('decode')->willThrowException($exception);

        return $provider;
    }

    private function configProviderDecoding(): DataLoaderConfigSerializerProvider
    {
        $provider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $provider->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

        return $provider;
    }

    private function decoder(?DataLoaderConfigSerializerProvider $configProvider = null): DraftLayoutDecoder
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

        return new DraftLayoutDecoder(
            new StoredElementCodec($configProvider ?? $this->configProviderDecoding()),
            new StoredTreeStyleNormalizer(new ElementStyleNormalizer($registry, new BoxSpacingNormalizer())),
            new ViolationConstraintMapper(),
        );
    }
}
