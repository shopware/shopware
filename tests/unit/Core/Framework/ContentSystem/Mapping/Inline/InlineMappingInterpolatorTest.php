<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Inline;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductMappingCandidateProvider;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingInterpolator;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingRuntimeResolver;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\ContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StubPathStruct;

/**
 * An unresolved candidate or value returns null so the render caller can omit the token; a resolved value returns
 * escaped text, including an actual empty string.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(InlineMappingInterpolator::class)]
class InlineMappingInterpolatorTest extends TestCase
{
    private const NAME_PATH = 'product.name';

    public function testResolvesACataloguedPathToItsText(): void
    {
        $ambient = ['product' => new StubPathStruct(name: 'Shirt')];

        static::assertSame('Shirt', $this->interpolator()->interpolate(self::NAME_PATH, $ambient, 'product', $this->element()));
    }

    public function testInlineProductTokenUsesTheOwningProvidersActiveTranslation(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setName('Raw variant name');
        $product->setTranslated(['name' => 'Inherited storefront name']);
        $candidates = new ContentSystemMappingCandidateRegistry([new ProductMappingCandidateProvider(new ProductContentLayoutDefinition())]);
        $interpolator = new InlineMappingInterpolator(
            new MappingRuntimeResolver(
                $candidates,
                $this->mappingInspector($candidates),
            ),
        );

        static::assertSame('Inherited storefront name', $interpolator->interpolate(self::NAME_PATH, ['product' => $product], 'product', $this->element()));
    }

    /**
     * Null marks an unavailable value; the render caller removes only the token and keeps surrounding authored text.
     */
    #[TestDox('resolves a stringifiable plugin candidate through its provider')]
    public function testResolvesAPluginCandidateThroughItsProvider(): void
    {
        $candidate = new MappingCandidate(
            path: 'plugin:example.message',
            label: 'currency code',
            description: '',
            group: 'plugin',
            valueType: 'string',
            source: new MappingSourceReference('plugin', 'example', path: 'message'),
        );
        $candidates = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidates->method('forRootSource')->willReturn([$candidate->source->displayName() => $candidate]);
        $candidates->method('resolveSource')->willReturn(MappingSourceResolution::resolved('EUR'));
        $interpolator = new InlineMappingInterpolator(
            new MappingRuntimeResolver(
                $candidates,
                $this->mappingInspector($candidates),
            ),
        );

        static::assertSame(
            'EUR',
            $interpolator->interpolate(
                $candidate->path,
                [],
                'product',
                $this->element(),
            ),
        );
    }

    public function testProviderFailurePropagatesDuringInterpolation(): void
    {
        $failure = new \RuntimeException('provider failed');
        $candidate = new MappingCandidate('product.name', 'name', '', 'basic', 'string');
        $candidates = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidates->method('forRootSource')->willReturn([$candidate->path => $candidate]);
        $candidates->method('resolveSource')->willThrowException($failure);
        $interpolator = new InlineMappingInterpolator(new MappingRuntimeResolver($candidates, $this->mappingInspector($candidates)));

        $this->expectExceptionObject($failure);

        $interpolator->interpolate(
            self::NAME_PATH,
            [],
            'product',
            $this->element(),
        );
    }

    #[TestDox('answers null for a path the catalogue does not offer')]
    public function testAnswersNullForAnUncataloguedPath(): void
    {
        $ambient = ['product' => new StubPathStruct(name: 'Shirt')];

        static::assertNull($this->interpolator()->interpolate('product.secret', $ambient, 'product', $this->element()));
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
     * An unavailable source makes the whole affected text property unavailable to the storefront.
     *
     * @param array<string, mixed> $ambient
     */
    #[DataProvider('unresolvableAmbientProvider')]
    #[TestDox('answers null for a catalogued path that resolved to nothing')]
    public function testAnswersNullForACataloguedButUnresolvedPath(array $ambient): void
    {
        static::assertNull($this->interpolator()->interpolate(self::NAME_PATH, $ambient, 'product', $this->element()));
    }

    public function testKeepsAResolvedEmptyStringDistinctFromMissingValue(): void
    {
        $ambient = ['product' => new StubPathStruct(name: '')];

        static::assertSame('', $this->interpolator()->interpolate(self::NAME_PATH, $ambient, 'product', $this->element()));
    }

    public function testRejectsAResolvedValueOutsideTheCandidatesEffectiveType(): void
    {
        $ambient = ['product' => new StubPathStruct(customFields: ['material' => 42])];

        static::assertNull($this->interpolator()->interpolate('product.customFields.material', $ambient, 'product', $this->element()));
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

        static::assertSame($expected, $this->interpolator()->interpolate(self::NAME_PATH, $ambient, 'product', $this->element()));
    }

    #[TestDox('answers null for a catalogued path whose value has no text form')]
    public function testAnswersNullForANonStringifiableCandidate(): void
    {
        $ambient = ['product' => new StubPathStruct(name: 'Shirt')];

        static::assertNull($this->interpolator()->interpolate('product.media', $ambient, 'product', $this->element()));
    }

    #[TestDox('reads a path through nested Structs')]
    public function testResolvesANestedPath(): void
    {
        $ambient = ['product' => new StubPathStruct(child: new StubPathStruct(name: 'Inner'))];

        static::assertSame('Inner', $this->interpolator()->interpolate('product.child.name', $ambient, 'product', $this->element()));
    }

    #[TestDox('answers nothing for a root source with no catalogue at all')]
    public function testAnswersNullForAnUnknownRootSource(): void
    {
        $ambient = ['product' => new StubPathStruct(name: 'Shirt')];

        static::assertNull($this->interpolator()->interpolate(self::NAME_PATH, $ambient, 'category', $this->element()));
    }

    public function testRejectsAnOversizedSourceBeforeEscaping(): void
    {
        $ambient = ['product' => new StubPathStruct(name: str_repeat('"', 1048577))];

        $this->expectExceptionObject(ContentSystemException::inlineMappingExpansionTooLarge(1048576, 'property'));

        $this->interpolator()->interpolate(self::NAME_PATH, $ambient, 'product', $this->element());
    }

    public function testRejectsAnOversizedEscapedReplacement(): void
    {
        $ambient = ['product' => new StubPathStruct(name: str_repeat('"', 174763))];

        $this->expectExceptionObject(ContentSystemException::inlineMappingExpansionTooLarge(1048576, 'property'));

        $this->interpolator()->interpolate(self::NAME_PATH, $ambient, 'product', $this->element());
    }

    private function interpolator(): InlineMappingInterpolator
    {
        $candidates = new ContentSystemMappingCandidateRegistry([new InlineTestRootProvider([
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
            'product.customFields.material' => new MappingCandidate(
                path: 'product.customFields.material',
                label: 'material',
                description: '',
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
        ])]);

        return new InlineMappingInterpolator(
            new MappingRuntimeResolver(
                $candidates,
                $this->mappingInspector($candidates),
            ),
        );
    }

    private function mappingInspector(AbstractContentSystemMappingCandidateRegistry $candidates): StoredMappingInspector
    {
        $types = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $types->method('has')->willReturn(false);

        return new StoredMappingInspector(
            $types,
            $candidates,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new InlineMappingTokenParser(),
        );
    }

    private function element(): StoredElement
    {
        return new StoredElement('el-1', 'Sw:Content:Text');
    }
}

/**
 * @internal
 */
class InlineTestRootProvider extends AbstractMappingCandidateProvider
{
    /**
     * @param array<string, MappingCandidate> $candidates
     */
    public function __construct(private readonly array $candidates)
    {
    }

    public static function registeredCandidateKeys(): array
    {
        return [];
    }

    public function supports(string $rootSource): bool
    {
        return $rootSource === 'product';
    }

    public function provide(string $rootSource): array
    {
        return array_values($this->candidates);
    }
}
