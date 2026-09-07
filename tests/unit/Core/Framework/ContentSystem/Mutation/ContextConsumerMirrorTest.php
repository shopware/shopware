<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementWiringDecoder;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\ContextConsumerMirror;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextConsumerMirror::class)]
class ContextConsumerMirrorTest extends TestCase
{
    private const PRODUCT_FQCN = 'Shopware\\Core\\Content\\Product\\SalesChannel\\SalesChannelProductEntity';

    #[TestDox('mirrors a parent-origin resolved reference as a parent-scope consumer on a created element')]
    public function testMirrorsParentOriginAsParentScopeConsumer(): void
    {
        [$tree, $resolutions, $created] = self::parentOriginReferenceScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        $consumers = $this->consumers($wired->roots[0]);
        static::assertArrayHasKey('product', $consumers);
        static::assertSame(ContextType::Single, $consumers['product']->type);
        static::assertTrue($consumers['product']->required);
        static::assertSame(ConsumerScope::Parent, $consumers['product']->scope);
        static::assertFalse($consumers['product']->redistribute);
        static::assertNull($consumers['product']->consumerAlias);
        static::assertNull($consumers['product']->propertyAlias);
    }

    #[TestDox('mirrors a root-origin resolved reference as a root-scope consumer on a created element')]
    public function testMirrorsRootOriginAsRootScopeConsumer(): void
    {
        [$tree, $resolutions, $created] = self::rootOriginReferenceScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        $consumers = $this->consumers($wired->roots[0]);
        static::assertArrayHasKey('product', $consumers);
        static::assertSame(ConsumerScope::Root, $consumers['product']->scope);
        static::assertFalse($consumers['product']->required);
        static::assertFalse($consumers['product']->redistribute);
    }

    #[TestDox('mirrors one consumer per proven reference when an element consumes two context keys')]
    public function testMirrorsOneConsumerPerProvenReference(): void
    {
        [$tree, $resolutions, $created] = self::twoProvenReferencesScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        $consumers = $this->consumers($wired->roots[0]);
        static::assertSame(['product', 'page'], array_keys($consumers));
        static::assertSame(ConsumerScope::Parent, $consumers['product']->scope);
        static::assertSame(ConsumerScope::Root, $consumers['page']->scope);
        static::assertSame(ContextType::Collection, $consumers['page']->type);
    }

    /**
     * @return iterable<string, array{0: CandidateOrigin, 1: bool, 2: ConsumerScope}>
     */
    public static function crossKeyOriginProvider(): iterable
    {
        yield 'parent origin' => [CandidateOrigin::Parent, true, ConsumerScope::Parent];
        yield 'root origin' => [CandidateOrigin::Root, false, ConsumerScope::Root];
    }

    #[DataProvider('crossKeyOriginProvider')]
    #[TestDox('keys a cross-key mirror by the resolved candidate context key and aliases it to the written property key')]
    public function testKeysTheConsumerByTheCandidateContextKey(CandidateOrigin $origin, bool $required, ConsumerScope $scope): void
    {
        [$tree, $resolutions, $created] = self::crossKeyReferenceScenario($origin, $required);

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        $consumers = $this->consumers($wired->roots[0]);
        static::assertSame(['product'], array_keys($consumers));
        static::assertSame('crossSellProduct', $consumers['product']->propertyAlias);
        static::assertSame($scope, $consumers['product']->scope);
    }

    #[TestDox('mirrors a cross-key reference even though the element provides the candidate context key, because the provider skip is matched against the written property key')]
    public function testMirrorsCrossKeyReferenceDespiteProvidingTheCandidateContextKey(): void
    {
        [$tree, $resolutions, $created] = self::crossKeyReferenceOntoProvidingElementScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        $consumers = $this->consumers($wired->roots[0]);
        static::assertSame(['product'], array_keys($consumers));
        static::assertSame('crossSellProduct', $consumers['product']->propertyAlias);
        static::assertSame(ConsumerScope::Parent, $consumers['product']->scope);
    }

    #[TestDox('mirrors an equal dotted key as a consumer with no propertyAlias, because a dotted consumer key without an alias is legal')]
    public function testMirrorsEqualDottedKeyWithoutAlias(): void
    {
        [$tree, $resolutions, $created] = self::equalDottedKeyScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        $consumers = $this->consumers($wired->roots[0]);
        static::assertSame(['product.name'], array_keys($consumers));
        static::assertNull($consumers['product.name']->propertyAlias);
    }

    #[TestDox('mirrors onto a created element nested in a slot, leaving its uncreated ancestors unwired')]
    public function testMirrorsOntoNestedCreatedElement(): void
    {
        [$tree, $resolutions, $created] = self::nestedCreatedElementScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        $wiredOuter = $wired->roots[0];
        $wiredInner = $wiredOuter->slots['content'][0];

        static::assertArrayHasKey('product', $this->consumers($wiredInner->slots['content'][0]));
        static::assertSame([], $this->consumers($wiredInner));
        static::assertSame([], $this->consumers($wiredOuter));
    }

    #[TestDox('wires the created element under the second root, leaving the untouched first root the identical instance')]
    public function testWiresCreatedElementUnderSecondRoot(): void
    {
        [$tree, $resolutions, $created] = self::createdElementUnderSecondRootScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertSame($tree->roots[0], $wired->roots[0]);
        static::assertArrayHasKey('product', $this->consumers($wired->roots[1]));
    }

    #[TestDox('mirrors only the first of two resolutions sharing a resolved context key, skipping the second')]
    public function testSameContextKeyResolutionsMirrorOnlyTheFirst(): void
    {
        [$tree, $resolutions, $created] = self::sharedContextKeyResolutionsScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        $consumers = $this->consumers($wired->roots[0]);
        static::assertSame(['product'], array_keys($consumers));
        static::assertFalse($consumers['product']->required);
    }

    #[TestDox('mirrors normally when the written property key does not collide with any existing consumer base key')]
    public function testMirrorsWhenNoBaseKeyCollides(): void
    {
        [$tree, $resolutions, $created] = self::noBaseKeyCollisionScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertSame(['other', 'product'], array_keys($this->consumers($wired->roots[0])));
    }

    #[TestDox('never overwrites a consumer the element already carries under the same key')]
    public function testNeverOverwritesExistingConsumer(): void
    {
        [$tree, $resolutions, $created] = self::existingConsumerSameKeyScenario();
        $authored = $this->consumers($tree->roots[0])['product'];

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertSame($authored, $this->consumers($wired->roots[0])['product']);
    }

    /**
     * The written property key and the existing consumer's key each reduce to a base key, and either side may
     * be the dotted one. The third row is the mirror image of the second: it dots the written key instead of
     * the existing consumer's, so a comparison that reduced only the existing-consumer side fails it.
     *
     * @return iterable<string, array{0: StoredElement, 1: PropertyResolution, 2: string}>
     */
    public static function baseKeyCollisionProvider(): iterable
    {
        yield 'existing consumer aliased to the written property' => [
            StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
                ->withConsumer('x', ContextType::Single, false, false, null, 'product')
                ->build(),
            self::reference('product', true, self::candidate(CandidateOrigin::Parent, 'y')),
            'x',
        ];

        yield 'existing dotted consumer key sharing the first segment' => [
            StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
                ->withConsumer('product.name', ContextType::Single)
                ->build(),
            self::reference('product', true, self::candidate(CandidateOrigin::Parent, 'y')),
            'product.name',
        ];

        yield 'dotted written key against an undotted existing consumer key' => [
            StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
                ->withConsumer('product', ContextType::Single)
                ->build(),
            self::reference('product.sku', true, self::candidate(CandidateOrigin::Parent, 'product.sku')),
            'product',
        ];
    }

    #[DataProvider('baseKeyCollisionProvider')]
    #[TestDox('skips a base-key collision against an existing consumer')]
    public function testSkipsBaseKeyCollision(StoredElement $element, PropertyResolution $resolution, string $survivingKey): void
    {
        [$tree, $resolutions, $created] = self::baseKeyCollisionScenario($element, $resolution);

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertSame($tree, $wired);
        static::assertSame([$survivingKey], array_keys($this->consumers($wired->roots[0])));
    }

    #[TestDox('skips a key the element already fills from a data requirement')]
    public function testSkipsKeyFilledByDataRequirement(): void
    {
        [$tree, $resolutions, $created] = self::dataRequirementFilledKeyScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertArrayNotHasKey('product', $this->consumers($wired->roots[0]));
        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    #[TestDox('skips a key the element itself provides')]
    public function testSkipsSelfProvidedKey(): void
    {
        [$tree, $resolutions, $created] = self::selfProvidedKeyScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertArrayNotHasKey('product', $this->consumers($wired->roots[0]));
        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    #[TestDox('leaves an element outside the created set unwired even when its reference is proven')]
    public function testLeavesUncreatedElementUnwired(): void
    {
        [$tree, $resolutions, $created] = self::uncreatedElementScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertSame($tree->roots[0]->contextDefinitions->getAllConsumers(), $this->consumers($wired->roots[0]));
        static::assertSame(['authored'], array_keys($this->consumers($wired->roots[0])));
    }

    #[TestDox('mirrors nothing for a cross-key resolution whose written property key contains a dot, because the decoder rejects a dotted propertyAlias')]
    public function testMirrorsNothingForCrossKeyResolutionWithDottedWrittenKey(): void
    {
        [$tree, $resolutions, $created] = self::dottedWrittenKeyScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertSame($tree, $wired);
        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    #[TestDox('mirrors nothing for a reference the resolution did not prove')]
    public function testMirrorsNothingForUnprovenReference(): void
    {
        [$tree, $resolutions, $created] = self::unprovenReferenceScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertSame($tree, $wired);
        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    /**
     * @return iterable<string, array{0: CandidateOrigin}>
     */
    public static function selfFillingOriginProvider(): iterable
    {
        yield 'loader origin' => [CandidateOrigin::Loader];
        yield 'stored origin' => [CandidateOrigin::Stored];
    }

    #[DataProvider('selfFillingOriginProvider')]
    #[TestDox('mirrors nothing for a reference a loader or the element own wiring already fills')]
    public function testMirrorsNothingForSelfFillingOrigin(CandidateOrigin $origin): void
    {
        [$tree, $resolutions, $created] = self::selfFillingOriginScenario($origin);

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertSame($tree, $wired);
        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function integerLikeGuardBoundaryProvider(): iterable
    {
        yield 'zero-padded digits ("007")' => ['007'];
        yield 'leading plus sign ("+1")' => ['+1'];
        yield 'leading whitespace (" 1")' => [' 1'];
        yield 'digit string past PHP_INT_MAX ("9223372036854775808")' => ['9223372036854775808'];
    }

    /**
     * None of these context keys is integer-like under the guard ((string) (int) $key === $key): each fails the
     * round trip through int, so the reference must still be mirrored. A guard reimplemented with ctype_digit()
     * would wrongly treat "007" and the past-PHP_INT_MAX digit string as integer-like and skip them.
     */
    #[DataProvider('integerLikeGuardBoundaryProvider')]
    #[TestDox('mirrors a cross-key reference whose candidate context key resembles an integer but fails the guard')]
    public function testMirrorsCrossKeyReferenceWithNonIntegerLikeContextKey(string $contextKey): void
    {
        [$tree, $resolutions, $created] = self::integerLikeGuardBoundaryScenario($contextKey);

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        $consumers = $this->consumers($wired->roots[0]);
        static::assertSame([$contextKey], array_keys($consumers));
        static::assertSame('product', $consumers[$contextKey]->propertyAlias);
    }

    /**
     * One row per guard in ContextConsumerMirror::consumerFor(), plus two extra rows for the integer-like
     * context-key guard to cover both a zero and a negative integer-like string, plus a row pinning that a
     * non-integer-like but dotted written property key ("1.5") is rejected by the dotted-propertyAlias guard,
     * never by the integer-like guard.
     *
     * @return iterable<string, array{0: PropertyResolution}>
     */
    public static function unmirrorableResolutionProvider(): iterable
    {
        yield 'primitive kind' => [new PropertyResolution(
            'product',
            PropertyKind::Primitive,
            true,
            'string',
            null,
            null,
            new ResolutionCandidate(CandidateOrigin::Parent, 'product', null, null, DistributionStrategy::Broadcast, ContextType::Single),
        )];

        yield 'proven candidate carrying no context type' => [new PropertyResolution(
            'product',
            PropertyKind::Reference,
            true,
            null,
            null,
            self::PRODUCT_FQCN,
            new ResolutionCandidate(CandidateOrigin::Parent, 'product', null, null, DistributionStrategy::Broadcast, null),
        )];

        yield 'proven candidate carrying an empty context key' => [new PropertyResolution(
            'product',
            PropertyKind::Reference,
            true,
            null,
            null,
            self::PRODUCT_FQCN,
            new ResolutionCandidate(CandidateOrigin::Parent, '', null, null, DistributionStrategy::Broadcast, ContextType::Single),
        )];

        yield 'proven candidate carrying an integer-like context key "0"' => [new PropertyResolution(
            'product',
            PropertyKind::Reference,
            true,
            null,
            null,
            self::PRODUCT_FQCN,
            new ResolutionCandidate(CandidateOrigin::Parent, '0', null, null, DistributionStrategy::Broadcast, ContextType::Single),
        )];

        yield 'proven candidate carrying an integer-like context key "-7"' => [new PropertyResolution(
            'product',
            PropertyKind::Reference,
            true,
            null,
            null,
            self::PRODUCT_FQCN,
            new ResolutionCandidate(CandidateOrigin::Parent, '-7', null, null, DistributionStrategy::Broadcast, ContextType::Single),
        )];

        yield 'written property key "1.5" is not integer-like but is dotted, rejected by the dotted-propertyAlias guard' => [new PropertyResolution(
            '1.5',
            PropertyKind::Reference,
            true,
            null,
            null,
            self::PRODUCT_FQCN,
            new ResolutionCandidate(CandidateOrigin::Parent, 'product', null, null, DistributionStrategy::Broadcast, ContextType::Single),
        )];
    }

    #[DataProvider('unmirrorableResolutionProvider')]
    #[TestDox('mirrors nothing for a resolution a consumer guard rejects')]
    public function testMirrorsNothingForUnmirrorableResolution(PropertyResolution $resolution): void
    {
        [$tree, $resolutions, $created] = self::unmirrorableResolutionScenario($resolution);

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertSame($tree, $wired);
        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    #[TestDox('returns the identical tree instance when the created set is empty')]
    public function testReturnsIdenticalTreeForEmptyCreatedSet(): void
    {
        [$tree, $resolutions, $created] = self::emptyCreatedSetScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertSame($tree, $wired);
    }

    #[TestDox('returns the identical tree instance when the created id names no element in the tree')]
    public function testReturnsIdenticalTreeForCreatedIdAbsentFromTree(): void
    {
        [$tree, $resolutions, $created] = self::createdIdAbsentFromTreeScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertSame($tree, $wired);
        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    #[TestDox('returns the identical tree instance when a created element has no resolutions at all')]
    public function testReturnsIdenticalTreeWhenCreatedElementHasNoResolutions(): void
    {
        [$tree, $resolutions, $created] = self::noResolutionsScenario();

        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $created);

        static::assertSame($tree, $wired);
    }

    /**
     * Every mirroring scenario this class exercises, as the three arguments {@see ContextConsumerMirror::apply()}
     * takes, so the decode-conformance test below runs over the same table the behavioural tests do. Each row
     * calls the same private static fixture method its originating test method uses, so the two cannot diverge. A
     * scenario that mirrors nothing today rides along deliberately: three of those guards — the dotted
     * `propertyAlias`, the base-key collision and the integer-like context key — exist because the consumer they
     * would otherwise write is one the decode gate refuses, so the guard failing is the only way that consumer
     * ever reaches this test. The remaining skip scenarios would write a legal consumer and are carried for
     * completeness.
     *
     * @return iterable<string, array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}>
     */
    public static function mirroringScenarioProvider(): iterable
    {
        yield 'a parent-origin reference' => self::parentOriginReferenceScenario();
        yield 'a root-origin reference' => self::rootOriginReferenceScenario();
        yield 'two proven references on one element' => self::twoProvenReferencesScenario();

        $crossKeyScenarioNames = [
            'parent origin' => 'a cross-key reference resolved off the parent chain',
            'root origin' => 'a cross-key reference resolved off the root context',
        ];

        foreach (self::crossKeyOriginProvider() as $name => [$origin, $required]) {
            yield $crossKeyScenarioNames[$name] => self::crossKeyReferenceScenario($origin, $required);
        }

        yield 'a cross-key reference onto an element providing the candidate context key' => self::crossKeyReferenceOntoProvidingElementScenario();
        yield 'an equal dotted key' => self::equalDottedKeyScenario();
        yield 'a created element nested in a slot' => self::nestedCreatedElementScenario();
        yield 'a created element under the second root' => self::createdElementUnderSecondRootScenario();
        yield 'two resolutions sharing one resolved context key' => self::sharedContextKeyResolutionsScenario();
        yield 'a written property key colliding with no existing consumer base key' => self::noBaseKeyCollisionScenario();
        yield 'a consumer the element already carries under the same key' => self::existingConsumerSameKeyScenario();

        foreach (self::baseKeyCollisionProvider() as $name => [$element, $resolution]) {
            yield $name => self::baseKeyCollisionScenario($element, $resolution);
        }

        yield 'a key the element already fills from a data requirement' => self::dataRequirementFilledKeyScenario();
        yield 'a key the element itself provides' => self::selfProvidedKeyScenario();
        yield 'an element outside the created set' => self::uncreatedElementScenario();
        yield 'a cross-key resolution whose written property key carries a dot' => self::dottedWrittenKeyScenario();
        yield 'a reference the resolution did not prove' => self::unprovenReferenceScenario();

        foreach (self::selfFillingOriginProvider() as $name => [$origin]) {
            yield $name => self::selfFillingOriginScenario($origin);
        }

        foreach (self::unmirrorableResolutionProvider() as $name => [$resolution]) {
            yield $name => self::unmirrorableResolutionScenario($resolution);
        }

        foreach (self::integerLikeGuardBoundaryProvider() as $name => [$contextKey]) {
            yield $name => self::integerLikeGuardBoundaryScenario($contextKey);
        }

        yield 'an empty created set' => self::emptyCreatedSetScenario();
        yield 'a created id naming no element in the tree' => self::createdIdAbsentFromTreeScenario();
        yield 'a created element with no resolutions at all' => self::noResolutionsScenario();
    }

    /**
     * A mirrored consumer is written straight onto an in-memory tree, so nothing in the mirror itself proves the
     * result survives the gate the client's next request puts it through: the element goes back out over HTTP and
     * comes back in through the same decode sequence {@see StoredElementCodec::decodeElement()} runs, reaching
     * {@see StoredElementWiringDecoder} — whose rules the mirror only hand-guards. This runs the encode shape the
     * response carries, through the JSON round trip the wire performs, into that decoder. An integer-like consumer
     * key is an integer array key before it is ever encoded, because PHP coerces it the moment the mirror assigns
     * it; the round trip is here because it is what the HTTP boundary does, and it carries that key through as an
     * integer for the decoder to refuse.
     *
     * The pin is that no decode step throws. The equality below is the narrower claim that encode and decode
     * agree on the map, and it can only fail where a consumer decodes into something other than what was written.
     *
     * @param array<string, list<PropertyResolution>> $resolutions
     * @param list<string> $createdElementIds
     */
    #[DataProvider('mirroringScenarioProvider')]
    #[TestDox('writes wiring the decode gate reads back for $_dataName')]
    public function testMirroredWiringSurvivesTheDecodeGate(StoredTree $tree, array $resolutions, array $createdElementIds): void
    {
        $wired = (new ContextConsumerMirror())->apply($tree, $resolutions, $createdElementIds);
        $decoder = new StoredElementWiringDecoder();

        foreach ($this->flatten($wired->roots) as $element) {
            $wireShape = $this->roundTrip($element);

            $providers = $decoder->decodeProviders($wireShape['providesContext'] ?? []);
            $consumers = $decoder->decodeConsumers($wireShape['acceptsContext'] ?? []);
            $decoder->rejectInvalidElementWiring($consumers, $providers);

            static::assertEquals(
                $element->contextDefinitions->getAllConsumers(),
                $consumers,
                \sprintf('Element "%s" does not decode back to the consumer map the mirror left on it.', $element->id)
            );
        }
    }

    private static function plainTree(): StoredTree
    {
        return new StoredTree([StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')->build()]);
    }

    /**
     * Shared by testSkipsBaseKeyCollision and mirroringScenarioProvider.
     *
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function baseKeyCollisionScenario(StoredElement $element, PropertyResolution $resolution): array
    {
        return [new StoredTree([$element]), ['p1' => [$resolution]], ['p1']];
    }

    /**
     * Shared by testMirrorsNothingForSelfFillingOrigin and mirroringScenarioProvider.
     *
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function selfFillingOriginScenario(CandidateOrigin $origin): array
    {
        return [
            self::plainTree(),
            ['p1' => [self::reference('product', true, self::candidate($origin, 'product'))]],
            ['p1'],
        ];
    }

    /**
     * Shared by testMirrorsNothingForUnmirrorableResolution and mirroringScenarioProvider.
     *
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function unmirrorableResolutionScenario(PropertyResolution $resolution): array
    {
        return [
            self::plainTree(),
            ['p1' => [$resolution]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function parentOriginReferenceScenario(): array
    {
        return [
            self::plainTree(),
            ['p1' => [self::reference('product', true, self::candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function rootOriginReferenceScenario(): array
    {
        return [
            self::plainTree(),
            ['p1' => [self::reference('product', false, self::candidate(CandidateOrigin::Root, 'product'))]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function twoProvenReferencesScenario(): array
    {
        return [
            self::plainTree(),
            ['p1' => [
                self::reference('product', true, self::candidate(CandidateOrigin::Parent, 'product')),
                self::reference('page', false, self::candidate(CandidateOrigin::Root, 'page', ContextType::Collection)),
            ]],
            ['p1'],
        ];
    }

    /**
     * Shared by {@see testKeysTheConsumerByTheCandidateContextKey} (via crossKeyOriginProvider) and
     * mirroringScenarioProvider.
     *
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function crossKeyReferenceScenario(CandidateOrigin $origin, bool $required): array
    {
        return [
            self::plainTree(),
            ['p1' => [self::reference('crossSellProduct', $required, self::candidate($origin, 'product'))]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function crossKeyReferenceOntoProvidingElementScenario(): array
    {
        return [
            new StoredTree([
                StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
                    ->withProvider('product', BroadcastDistributionConfig::simple())
                    ->build(),
            ]),
            ['p1' => [self::reference('crossSellProduct', true, self::candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function equalDottedKeyScenario(): array
    {
        return [
            self::plainTree(),
            ['p1' => [self::reference('product.name', true, self::candidate(CandidateOrigin::Parent, 'product.name'))]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function nestedCreatedElementScenario(): array
    {
        return [
            new StoredTree([
                StoredElementBuilder::create('Sw:Grid:Container', 'outer')->withSlot('content', [
                    StoredElementBuilder::create('Sw:Grid:Container', 'inner')->withSlot('content', [
                        StoredElementBuilder::create('Sw:Product:PriceDisplay', 'price')->build(),
                    ])->build(),
                ])->build(),
            ]),
            ['price' => [self::reference('product', false, self::candidate(CandidateOrigin::Parent, 'product'))]],
            ['price'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function createdElementUnderSecondRootScenario(): array
    {
        return [
            new StoredTree([
                StoredElementBuilder::create('Sw:Grid:Container', 'root0')->build(),
                StoredElementBuilder::create('Sw:Product:PriceDisplay', 'root1')->build(),
            ]),
            ['root1' => [self::reference('product', true, self::candidate(CandidateOrigin::Parent, 'product'))]],
            ['root1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function sharedContextKeyResolutionsScenario(): array
    {
        return [
            self::plainTree(),
            ['p1' => [
                self::reference('firstProperty', false, self::candidate(CandidateOrigin::Parent, 'product')),
                self::reference('secondProperty', true, self::candidate(CandidateOrigin::Parent, 'product')),
            ]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function noBaseKeyCollisionScenario(): array
    {
        return [
            new StoredTree([
                StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
                    ->withConsumer('other', ContextType::Single)
                    ->build(),
            ]),
            ['p1' => [self::reference('product', true, self::candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function existingConsumerSameKeyScenario(): array
    {
        return [
            new StoredTree([
                StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
                    ->withConsumer('product', ContextType::Collection, true, false, null, 'item')
                    ->build(),
            ]),
            ['p1' => [self::reference('product', false, self::candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function dataRequirementFilledKeyScenario(): array
    {
        return [
            new StoredTree([
                StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
                    ->withDataRequirement('product', 'entity', new StubLoaderConfig())
                    ->build(),
            ]),
            ['p1' => [self::reference('product', false, self::candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function selfProvidedKeyScenario(): array
    {
        return [
            new StoredTree([
                StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
                    ->withProvider('product', BroadcastDistributionConfig::simple())
                    ->build(),
            ]),
            ['p1' => [self::reference('product', false, self::candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function uncreatedElementScenario(): array
    {
        return [
            new StoredTree([
                StoredElementBuilder::create('Sw:Product:PriceDisplay', 'p1')
                    ->withConsumer('authored', ContextType::Single)
                    ->build(),
            ]),
            ['p1' => [
                self::reference('product', true, self::candidate(CandidateOrigin::Parent, 'product')),
                self::reference('page', true, self::candidate(CandidateOrigin::Root, 'page')),
            ]],
            ['other-id'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function dottedWrittenKeyScenario(): array
    {
        return [
            self::plainTree(),
            ['p1' => [self::reference('product.name', true, self::candidate(CandidateOrigin::Parent, 'product'))]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function unprovenReferenceScenario(): array
    {
        return [
            self::plainTree(),
            ['p1' => [self::reference('product', true, null)]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function integerLikeGuardBoundaryScenario(string $contextKey): array
    {
        return [
            self::plainTree(),
            ['p1' => [self::reference('product', true, self::candidate(CandidateOrigin::Parent, $contextKey))]],
            ['p1'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function emptyCreatedSetScenario(): array
    {
        return [
            self::plainTree(),
            ['p1' => [self::reference('product', true, self::candidate(CandidateOrigin::Parent, 'product'))]],
            [],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function createdIdAbsentFromTreeScenario(): array
    {
        return [
            self::plainTree(),
            ['p1' => [self::reference('product', true, self::candidate(CandidateOrigin::Parent, 'product'))]],
            ['ghost'],
        ];
    }

    /**
     * @return array{0: StoredTree, 1: array<string, list<PropertyResolution>>, 2: list<string>}
     */
    private static function noResolutionsScenario(): array
    {
        return [
            self::plainTree(),
            [],
            ['p1'],
        ];
    }

    /**
     * The encoded element put through the JSON round trip the HTTP boundary performs, so PHP's array-key
     * coercion applies to every wiring key exactly as it does on a real request body.
     *
     * @return array<array-key, mixed>
     */
    private function roundTrip(StoredElement $element): array
    {
        return (array) json_decode(json_encode($element->jsonSerialize(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<StoredElement> $elements
     *
     * @return list<StoredElement>
     */
    private function flatten(array $elements): array
    {
        $flat = [];

        foreach ($elements as $element) {
            $flat[] = $element;

            foreach ($element->slots as $children) {
                foreach ($this->flatten($children) as $descendant) {
                    $flat[] = $descendant;
                }
            }
        }

        return $flat;
    }

    /**
     * @return array<string, ContextConsumer>
     */
    private function consumers(StoredElement $element): array
    {
        return $element->contextDefinitions->getAllConsumers();
    }

    private static function reference(string $key, bool $required, ?ResolutionCandidate $resolved): PropertyResolution
    {
        return new PropertyResolution($key, PropertyKind::Reference, $required, null, null, self::PRODUCT_FQCN, $resolved);
    }

    private static function candidate(CandidateOrigin $origin, string $contextKey, ContextType $type = ContextType::Single): ResolutionCandidate
    {
        return new ResolutionCandidate($origin, $contextKey, null, null, DistributionStrategy::Broadcast, $type);
    }
}
