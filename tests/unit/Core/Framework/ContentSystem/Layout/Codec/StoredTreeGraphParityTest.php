<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionConstraintDeriver;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContextConsumersFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContextProvidersFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\DataRequirementsFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ElementSlotsFieldSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ElementStyleFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The codec and the element serializer graph it replaces read the same stored payloads, and until both existed
 * nothing compared them. One table of payloads, each with the verdict both sides owe it, measures that: what
 * each side accepts, and what shape it produces for what it accepts.
 *
 * A row is {@see AGREE_ACCEPT} when both read the payload and produce the same canonical array, and
 * {@see AGREE_REJECT} when both refuse it. {@see CODEC_STRICTER} is the one sanctioned disagreement: the codec
 * closes key sets and rejects wiring keys the graph never examined, so it turns away a payload the graph reads.
 * That direction only costs a write; the opposite direction would let the graph write a row the codec could
 * never read back, so no row carries it.
 *
 * {@see GRAPH_TYPE_ERRORS} is not a disagreement about the verdict but about how it is delivered: the codec
 * refuses the payload as a domain error a boundary can turn into a rejection, while the graph reaches a
 * signature that cannot take the value and raises a PHP {@see \TypeError}, which no boundary catches. Each such
 * row is a defect in the older path that this comparison exists to surface.
 *
 * Only the per-element halves are compared. The forest level has no counterpart to compare against: the list
 * field serializer used to own it and now delegates it to {@see StoredTreeCodec}, so there is one implementation
 * of it rather than two.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredTreeCodec::class)]
#[CoversClass(StoredElementCodec::class)]
class StoredTreeGraphParityTest extends TestCase
{
    private const AGREE_ACCEPT = 'read by both, same canonical shape';

    private const AGREE_REJECT = 'refused by both';

    private const CODEC_STRICTER = 'refused by the codec, still read by the graph';

    private const GRAPH_TYPE_ERRORS = 'refused by the codec, a PHP type error on the graph';

    /**
     * The deepest slot nesting the graph fixture below can walk. Each level needs its own serializer instance,
     * because the graph's slot serializer holds the element serializer it recurses through.
     */
    private const GRAPH_DEPTH = 4;

    /**
     * @param list<array<array-key, mixed>> $forest
     */
    #[DataProvider('agreedAcceptanceProvider')]
    #[TestDox('both read $_dataName and canonicalize it the same way')]
    public function testBothSidesReadAPayloadAndAgreeOnItsCanonicalShape(array $forest): void
    {
        static::assertNull($this->codecRejection($forest), 'The codec refused a payload the graph reads.');
        static::assertNull($this->graphRejection($forest), 'The graph refused a payload the codec reads.');

        static::assertSame(
            $this->graphShape($forest),
            $this->codecShape($forest),
            'Both sides read the payload but canonicalize it differently, so a value would change shape the '
            . 'first time the codec rewrites a row the graph wrote.'
        );
    }

    /**
     * @param list<array<array-key, mixed>> $forest
     */
    #[DataProvider('agreedRejectionProvider')]
    #[TestDox('both refuse $_dataName')]
    public function testBothSidesRefuseAPayload(array $forest): void
    {
        static::assertNotNull($this->codecRejection($forest), 'The codec read a payload the graph refuses.');
        static::assertNotNull($this->graphRejection($forest), 'The graph read a payload the codec refuses.');
    }

    /**
     * @param list<array<array-key, mixed>> $forest
     */
    #[DataProvider('codecStricterProvider')]
    #[TestDox('the codec alone refuses $_dataName')]
    public function testTheCodecAloneRefusesAPayloadTheGraphStillReads(array $forest, string $reason): void
    {
        static::assertNotNull(
            $this->codecRejection($forest),
            'The codec no longer refuses a payload it deliberately refuses: ' . $reason
        );

        static::assertNull(
            $this->graphRejection($forest),
            'The graph now refuses a payload it deliberately reads: ' . $reason
        );
    }

    /**
     * @param list<array<array-key, mixed>> $forest
     */
    #[DataProvider('graphTypeErrorProvider')]
    #[TestDox('the codec refuses $_dataName as a domain error where the graph raises a PHP type error')]
    public function testTheCodecRefusesAPayloadTheGraphCannotEvenTypeCheck(array $forest, string $reason): void
    {
        static::assertNotNull(
            $this->codecRejection($forest),
            'The codec no longer refuses a payload it deliberately refuses: ' . $reason
        );

        $this->expectException(\TypeError::class);

        $this->decodeThroughGraph($forest);
    }

    /**
     * @return iterable<string, array{list<array<array-key, mixed>>}>
     */
    public static function agreedAcceptanceProvider(): iterable
    {
        foreach (self::parityTable() as $name => [$forest, $verdict]) {
            if ($verdict === self::AGREE_ACCEPT) {
                yield $name => [$forest];
            }
        }
    }

    /**
     * @return iterable<string, array{list<array<array-key, mixed>>}>
     */
    public static function agreedRejectionProvider(): iterable
    {
        foreach (self::parityTable() as $name => [$forest, $verdict]) {
            if ($verdict === self::AGREE_REJECT) {
                yield $name => [$forest];
            }
        }
    }

    /**
     * @return iterable<string, array{list<array<array-key, mixed>>, string}>
     */
    public static function codecStricterProvider(): iterable
    {
        foreach (self::parityTable() as $name => [$forest, $verdict, $reason]) {
            if ($verdict === self::CODEC_STRICTER) {
                yield $name => [$forest, $reason];
            }
        }
    }

    /**
     * @return iterable<string, array{list<array<array-key, mixed>>, string}>
     */
    public static function graphTypeErrorProvider(): iterable
    {
        foreach (self::parityTable() as $name => [$forest, $verdict, $reason]) {
            if ($verdict === self::GRAPH_TYPE_ERRORS) {
                yield $name => [$forest, $reason];
            }
        }
    }

    /**
     * The one table. Each row is a stored forest, the verdict both sides owe it, and — for a sanctioned
     * divergence only — why the codec is allowed to be the stricter of the two.
     *
     * @return iterable<string, array{list<array<array-key, mixed>>, string, string}>
     */
    public static function parityTable(): iterable
    {
        yield 'a forest with no roots' => [[], self::AGREE_ACCEPT, ''];

        yield 'an element carrying nothing but its two required keys' => [
            [['id' => 'root-1', 'component' => 'core:text']],
            self::AGREE_ACCEPT,
            '',
        ];

        yield 'every scalar and container property value' => [
            self::forest(['properties' => [
                'title' => 'Hello',
                'count' => 3,
                'ratio' => 1.5,
                'flag' => true,
                'nothing' => null,
                'tags' => ['a', 'b'],
                'meta' => ['unit' => 'px'],
                'empty' => [],
            ]]),
            self::AGREE_ACCEPT,
            '',
        ];

        yield 'a slot carrying one child' => [
            self::forest(['slots' => ['main' => [['id' => 'child-1', 'component' => 'core:text', 'properties' => []]]]]),
            self::AGREE_ACCEPT,
            '',
        ];

        yield 'a slot carrying no children' => [
            self::forest(['slots' => ['main' => []]]),
            self::AGREE_ACCEPT,
            '',
        ];

        yield 'a data requirement whose key falls back to its map key' => [
            self::forest(['dataRequirements' => [
                'products' => ['source' => 'entity', 'config' => ['entity' => 'product']],
            ]]),
            self::AGREE_ACCEPT,
            '',
        ];

        yield 'a context provider and a context consumer' => [
            self::forest([
                'providesContext' => ['product' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => [
                    'items' => [
                        'type' => 'collection',
                        'required' => true,
                        'redistribute' => true,
                        'consumerAlias' => 'inner',
                        'propertyAlias' => 'entries',
                    ],
                ],
            ]),
            self::AGREE_ACCEPT,
            '',
        ];

        yield 'a style option carrying a breakpoint map and a flat value' => [
            self::forest(['style' => ['col-span' => ['md' => 6], 'display' => 'block']]),
            self::AGREE_ACCEPT,
            '',
        ];

        yield 'an attributed specification' => [
            self::forest(['attributedSpecifications' => ['image' => 'core:product-image']]),
            self::AGREE_ACCEPT,
            '',
        ];

        yield 'an element with no id' => [[['component' => 'core:text']], self::AGREE_REJECT, ''];

        yield 'an element whose id is not a string' => [
            [['id' => 12, 'component' => 'core:text']],
            self::AGREE_REJECT,
            '',
        ];

        yield 'an element with no component' => [[['id' => 'root-1']], self::AGREE_REJECT, ''];

        yield 'an element whose component is not a string' => [
            [['id' => 'root-1', 'component' => 12]],
            self::AGREE_REJECT,
            '',
        ];

        yield 'a non-array properties' => [self::forest(['properties' => 'x']), self::AGREE_REJECT, ''];

        yield 'a consumer alias without redistribution' => [
            self::forest(['acceptsContext' => [
                'items' => ['type' => 'single', 'required' => true, 'consumerAlias' => 'inner'],
            ]]),
            self::AGREE_REJECT,
            '',
        ];

        yield 'a property alias carrying dot notation' => [
            self::forest(['acceptsContext' => [
                'items' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'product.name'],
            ]]),
            self::AGREE_REJECT,
            '',
        ];

        yield 'an unknown element key' => [
            self::forest(['elements' => []]),
            self::CODEC_STRICTER,
            'the codec closes the element key set, so a field the stored shape does not carry fails decode; '
            . 'the graph reads only the keys it knows and drops the rest without saying so',
        ];

        yield 'an unknown key in a data requirement entry' => [
            self::forest(['dataRequirements' => ['products' => [
                'source' => 'entity',
                'config' => ['entity' => 'product'],
                'limit' => 5,
            ]]]),
            self::CODEC_STRICTER,
            'the codec closes the data requirement key set for the same reason it closes the element one',
        ];

        yield 'an unknown key in a consumer entry' => [
            self::forest(['acceptsContext' => [
                'items' => ['type' => 'single', 'required' => true, 'fallback' => 'none'],
            ]]),
            self::CODEC_STRICTER,
            'the codec closes the consumer key set for the same reason it closes the element one',
        ];

        // PHP maps a numeric JSON member name back to an integer array key, which no wiring map may carry.
        yield 'a numeric key in properties' => [
            self::forest(['properties' => [12 => 'x']]),
            self::GRAPH_TYPE_ERRORS,
            'the stored element constructor rejects a numeric wiring key as a domain error, while the older '
            . 'model has no such check and hands the integer key straight to a string-typed setter',
        ];

        yield 'a numeric key in dataRequirements' => [
            self::forest(['dataRequirements' => [7 => ['source' => 'entity', 'config' => []]]]),
            self::GRAPH_TYPE_ERRORS,
            'the stored element constructor rejects a numeric wiring key as a domain error, while the older '
            . 'path hands the integer map key to a string-typed data requirement constructor',
        ];

        yield 'a numeric key in slots' => [
            self::forest(['slots' => [3 => [['id' => 'child-1', 'component' => 'core:text']]]]),
            self::CODEC_STRICTER,
            'the stored element constructor rejects a numeric wiring key',
        ];

        yield 'a null data requirement entry' => [
            self::forest(['dataRequirements' => ['products' => null]]),
            self::CODEC_STRICTER,
            'the codec treats a malformed entry inside a structural container as unreadable storage; the graph '
            . 'skips it and reads the element without it',
        ];

        yield 'a null context consumer entry' => [
            self::forest(['acceptsContext' => ['items' => null]]),
            self::CODEC_STRICTER,
            'the codec treats a malformed entry inside a structural container as unreadable storage; the graph '
            . 'skips it and reads the element without it',
        ];

        yield 'a null nested slot child' => [
            self::forest(['slots' => ['main' => [null]]]),
            self::CODEC_STRICTER,
            'the codec treats a malformed entry inside a structural container as unreadable storage; the graph '
            . 'skips it and reads the element without it',
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return list<array<array-key, mixed>>
     */
    private static function forest(array $overrides): array
    {
        return [array_merge(['id' => 'root-1', 'component' => 'core:text'], $overrides)];
    }

    /**
     * The codec's verdict on one payload: `null` when it reads, the rejection message when it does not.
     *
     * @param list<array<array-key, mixed>> $forest
     */
    private function codecRejection(array $forest): ?string
    {
        try {
            $this->codec()->decode($forest);
        } catch (ContentSystemException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * The graph's verdict on the same payload, element by element.
     *
     * @param list<array<array-key, mixed>> $forest
     */
    private function graphRejection(array $forest): ?string
    {
        try {
            $this->decodeThroughGraph($forest);
        } catch (ContentSystemException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * @param list<array<array-key, mixed>> $forest
     *
     * @return list<array<string, mixed>>
     */
    private function codecShape(array $forest): array
    {
        return $this->codec()->encode($this->codec()->decode($forest));
    }

    /**
     * @param list<array<array-key, mixed>> $forest
     *
     * @return list<array<string, mixed>>
     */
    private function graphShape(array $forest): array
    {
        $serializer = $this->graph();

        return array_map(
            static fn (array $element): array => $serializer->serializeContentElement($serializer->decodeElement($element)),
            $forest
        );
    }

    /**
     * @param list<array<array-key, mixed>> $forest
     */
    private function decodeThroughGraph(array $forest): void
    {
        $serializer = $this->graph();

        foreach ($forest as $element) {
            $serializer->decodeElement($element);
        }
    }

    /**
     * Loader config semantics stay out of the comparison: neither side is being measured on what a given
     * source's config must contain, so both get the same provider that accepts any config.
     */
    private function codec(): StoredTreeCodec
    {
        return new StoredTreeCodec(new StoredElementCodec($this->configProvider()));
    }

    /**
     * The element serializer graph, chained {@see GRAPH_DEPTH} levels deep. Its slot serializer holds the
     * element serializer it recurses through, so the two cannot be built as one cycle; each level wraps the
     * one below it, and the innermost level's slot serializer recurses into a serializer that reads no slots.
     */
    private function graph(): ContentElementFieldSerializer
    {
        $serializer = $this->graphLevel(null);

        for ($level = 1; $level < self::GRAPH_DEPTH; ++$level) {
            $serializer = $this->graphLevel($serializer);
        }

        return $serializer;
    }

    private function graphLevel(?ContentElementFieldSerializer $below): ContentElementFieldSerializer
    {
        $validator = static::createStub(ValidatorInterface::class);
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $slots = new ElementSlotsFieldSerializer(
            $validator,
            $definitionRegistry,
            $below ?? static::createStub(ContentElementFieldSerializer::class)
        );

        return new ContentElementFieldSerializer(
            $validator,
            $definitionRegistry,
            new DataRequirementsFieldSerializer($validator, $definitionRegistry, $this->configProvider()),
            new ContextProvidersFieldSerializer($validator, $definitionRegistry),
            new ContextConsumersFieldSerializer($validator, $definitionRegistry),
            $slots,
            $this->styleSerializer($validator, $definitionRegistry)
        );
    }

    private function styleSerializer(ValidatorInterface $validator, DefinitionInstanceRegistry $definitionRegistry): ElementStyleFieldSerializer
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([]);

        return new ElementStyleFieldSerializer($validator, $definitionRegistry, $registry, new StyleOptionConstraintDeriver());
    }

    private function configProvider(): DataLoaderConfigSerializerProvider
    {
        $configProvider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $configProvider->method('decode')->willReturn(new StubLoaderConfig());

        return $configProvider;
    }
}
