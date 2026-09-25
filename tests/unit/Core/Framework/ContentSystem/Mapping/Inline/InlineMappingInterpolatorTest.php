<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Inline;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextPathResolver;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingInterpolator;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\ContentSystemPropertyProjectionRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubPathStruct;
use Shopware\Core\Test\Stub\ContentSystem\StubUppercaseProjection;

/**
 * The three outcomes this class has to keep apart: a path the catalogue never offered (null, so the token survives),
 * a catalogued path that resolved to nothing (empty string), and a resolved one (escaped text).
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(InlineMappingInterpolator::class)]
class InlineMappingInterpolatorTest extends TestCase
{
    private const NAME_PATH = 'product.name';

    /**
     * `nonStructProp` is just a plain string member on the stub; the name refers to its role in other tests.
     */
    private const PROJECTED_PATH = 'product.nonStructProp';

    public function testResolvesACataloguedPathToItsText(): void
    {
        $ambient = ['product' => new StubPathStruct(name: 'Shirt')];

        static::assertSame('Shirt', $this->interpolator()->interpolate(self::NAME_PATH, $ambient, 'product', 'el-1'));
    }

    /**
     * Null rather than an empty string, because the caller reads null as "leave the author's characters alone". An
     * uncatalogued token was never a mapping, so deleting it would be presumptuous and a typo would go unnoticed.
     */
    #[TestDox('answers null for a path the catalogue does not offer, which leaves the token verbatim')]
    public function testAnswersNullForAnUncataloguedPath(): void
    {
        $ambient = ['product' => new StubPathStruct(name: 'Shirt')];

        static::assertNull($this->interpolator()->interpolate('product.secret', $ambient, 'product', 'el-1'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unresolvableAmbientProvider(): iterable
    {
        yield 'the ambient key is absent' => [[]];
        yield 'the ambient value is null' => [['product' => null]];
        yield 'the ambient value is not a Struct' => [['product' => 'a string']];
        yield 'the path resolves to null inside the Struct' => [['product' => new StubPathStruct()]];
    }

    /**
     * Empty, not null: a mapping DID run, it just found nothing. There is no authored value to fall back to the way a
     * whole-field mapping has, because here the surrounding text is the authored value.
     *
     * @param array<string, mixed> $ambient
     */
    #[DataProvider('unresolvableAmbientProvider')]
    #[TestDox('renders a catalogued path that resolved to nothing as empty')]
    public function testRendersACataloguedButUnresolvedPathAsEmpty(array $ambient): void
    {
        static::assertSame('', $this->interpolator()->interpolate(self::NAME_PATH, $ambient, 'product', 'el-1'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function escapingProvider(): iterable
    {
        yield 'angle brackets' => ['<script>alert(1)</script>', '&lt;script&gt;alert(1)&lt;/script&gt;'];
        yield 'double quotes' => ['say "hi"', 'say &quot;hi&quot;'];
        yield 'single quotes' => ['it\'s', 'it&#039;s'];
        yield 'an ampersand' => ['Bob & Co', 'Bob &amp; Co'];
        yield 'nothing to escape' => ['Plain Shirt', 'Plain Shirt'];
    }

    /**
     * The value reaches a Twig template that renders the property with `|raw`, so escaping here is what stands
     * between a mapped entity field and stored XSS.
     */
    #[DataProvider('escapingProvider')]
    #[TestDox('escapes the resolved value for text context')]
    public function testEscapesTheResolvedValue(string $stored, string $expected): void
    {
        $ambient = ['product' => new StubPathStruct(name: $stored)];

        static::assertSame($expected, $this->interpolator()->interpolate(self::NAME_PATH, $ambient, 'product', 'el-1'));
    }

    #[TestDox('renders a catalogued path whose value has no text form as empty')]
    public function testRendersANonStringifiableCandidateAsEmpty(): void
    {
        $ambient = ['product' => new StubPathStruct(name: 'Shirt')];

        static::assertSame('', $this->interpolator()->interpolate('product.media', $ambient, 'product', 'el-1'));
    }

    #[TestDox('applies the projection the candidate declares before writing the value into the text')]
    public function testAppliesTheCandidatesProjection(): void
    {
        $ambient = ['product' => new StubPathStruct(nonStructProp: 'Shirt')];

        static::assertSame('SHIRT', $this->interpolator()->interpolate(self::PROJECTED_PATH, $ambient, 'product', 'el-1'));
    }

    /**
     * Stored data can outlive the plugin that registered its projection. Rendering empty keeps the page up, which
     * matches how a whole-field mapping degrades.
     */
    #[TestDox('renders empty when the declared projection is no longer registered')]
    public function testRendersEmptyWhenTheProjectionIsMissing(): void
    {
        $ambient = ['product' => new StubPathStruct(nonStructProp: 'Shirt')];
        $interpolator = $this->interpolator(projections: []);

        static::assertSame('', $interpolator->interpolate(self::PROJECTED_PATH, $ambient, 'product', 'el-1'));
    }

    /**
     * A projection declares its input type as a contract and is entitled to assume it, so a value outside that type
     * must never reach it. This is the case where a candidate's declared type and the data's actual shape disagree,
     * which stored data outliving its code can produce.
     */
    #[TestDox('renders empty rather than calling a projection with a value outside its declared input type')]
    public function testRendersEmptyWhenTheValueIsOutsideTheProjectionsInputType(): void
    {
        $ambient = ['product' => new StubPathStruct(nonStructProp: 42)];

        static::assertSame('', $this->interpolator()->interpolate(self::PROJECTED_PATH, $ambient, 'product', 'el-1'));
    }

    #[TestDox('reads a path through nested Structs')]
    public function testResolvesANestedPath(): void
    {
        $ambient = ['product' => new StubPathStruct(child: new StubPathStruct(name: 'Inner'))];

        static::assertSame('Inner', $this->interpolator()->interpolate('product.child.name', $ambient, 'product', 'el-1'));
    }

    #[TestDox('answers nothing for a root source with no catalogue at all')]
    public function testAnswersNullForAnUnknownRootSource(): void
    {
        $ambient = ['product' => new StubPathStruct(name: 'Shirt')];

        static::assertNull($this->interpolator()->interpolate(self::NAME_PATH, $ambient, 'category', 'el-1'));
    }

    /**
     * @param list<StubUppercaseProjection>|null $projections
     */
    private function interpolator(?array $projections = null): InlineMappingInterpolator
    {
        $candidates = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidates->method('forRootSource')->willReturnCallback(
            static fn (string $rootSource): array => $rootSource !== 'product' ? [] : [
                self::NAME_PATH => new MappingCandidate(
                    path: self::NAME_PATH,
                    label: 'a label',
                    description: 'a description',
                    group: 'basic',
                    valueType: 'string',
                ),
                'product.child.name' => new MappingCandidate(
                    path: 'product.child.name',
                    label: 'a label',
                    description: 'a description',
                    group: 'basic',
                    valueType: 'string',
                ),
                'product.media' => new MappingCandidate(
                    path: 'product.media',
                    label: 'a label',
                    description: 'a description',
                    group: 'media',
                    valueType: MediaEntity::class,
                ),
                // Key and path always agree: the real registry is documented as keyed BY path.
                self::PROJECTED_PATH => new MappingCandidate(
                    path: self::PROJECTED_PATH,
                    label: 'a label',
                    description: 'a description',
                    group: 'basic',
                    valueType: 'string',
                    projection: StubUppercaseProjection::NAME,
                ),
            ]
        );

        return new InlineMappingInterpolator(
            $candidates,
            new ContentSystemPropertyProjectionRegistry($projections ?? [new StubUppercaseProjection()]),
            new ContextPathResolver(),
        );
    }
}
