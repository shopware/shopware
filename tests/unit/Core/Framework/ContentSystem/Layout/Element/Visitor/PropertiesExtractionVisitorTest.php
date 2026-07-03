<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Visitor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Visitor\PropertiesExtractionVisitor;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubExtractorEntity;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Shopware\Core\Test\Stub\ContentSystem\StubStruct;

/**
 * @internal
 */
#[CoversClass(PropertiesExtractionVisitor::class)]
class PropertiesExtractionVisitorTest extends TestCase
{
    private DataLoaderConfigSerializerProvider&Stub $configSerializerProvider;

    private PropertiesExtractionVisitor $visitor;

    protected function setUp(): void
    {
        $this->configSerializerProvider = static::createStub(DataLoaderConfigSerializerProvider::class);
        // A real ConfigCanonicalizer: these tests exercise the config-hash canonicalization behavior, so the
        // canonicalizer must run for real rather than be stubbed out.
        $this->visitor = new PropertiesExtractionVisitor($this->configSerializerProvider, new ConfigCanonicalizer());
    }

    #[TestDox('extracts scalar property with scalar prefix and element-key-specific hash')]
    public function testExtractsScalarProperty(): void
    {
        $element = ContentElementBuilder::create('text', 'elem-1')
            ->withProperty('title', 'Hello World')
            ->build();

        $this->visitor->enter($element);
        $this->visitor->leave($element);

        $data = $this->visitor->getData();
        $assignments = $this->visitor->getAssignments();

        static::assertCount(1, $data);
        static::assertArrayHasKey('elem-1', $assignments);
        static::assertArrayHasKey('title', $assignments['elem-1']);

        $refId = $assignments['elem-1']['title'];
        static::assertStringStartsWith('scalar:', $refId);
        static::assertSame('Hello World', $data[$refId]);
    }

    #[TestDox('extracts array property with array prefix')]
    public function testExtractsArrayProperty(): void
    {
        $element = ContentElementBuilder::create('list', 'elem-2')
            ->withProperty('items', ['a', 'b', 'c'])
            ->build();

        $this->visitor->enter($element);
        $this->visitor->leave($element);

        $data = $this->visitor->getData();
        $assignments = $this->visitor->getAssignments();

        $refId = $assignments['elem-2']['items'];
        static::assertStringStartsWith('array:', $refId);
        static::assertSame(['a', 'b', 'c'], $data[$refId]);
    }

    /**
     * @param non-empty-string $expectedPrefix
     */
    #[DataProvider('extractsObjectWithRequirementProvider')]
    #[TestDox('extracts $_dataName')]
    public function testExtractsObjectWithRequirement(object $value, string $expectedPrefix): void
    {
        $config = new StubLoaderConfig();

        $this->configSerializerProvider->method('encode')
            ->willReturn(['type' => 'entity', 'id' => 'abc']);

        $element = ContentElementBuilder::create('card', 'elem-3')
            ->withProperty('product', $value)
            ->withDataRequirement('product', 'entity', $config)
            ->build();

        $this->visitor->enter($element);
        $this->visitor->leave($element);

        $data = $this->visitor->getData();
        $assignments = $this->visitor->getAssignments();

        $refId = $assignments['elem-3']['product'];
        static::assertStringStartsWith($expectedPrefix, $refId);
        static::assertSame($value, $data[$refId]);
    }

    /**
     * @return iterable<string, array{object, non-empty-string}>
     */
    public static function extractsObjectWithRequirementProvider(): iterable
    {
        yield 'entity uses apiAlias and uniqueIdentifier' => [new StubExtractorEntity('entity-abc'), 'test_entity:entity-abc:'];
        yield 'struct uses apiAlias and splObjectId' => [new StubStruct(), 'test_struct:'];
        yield 'plain object uses object prefix and splObjectId' => [new \stdClass(), 'object:'];
    }

    #[TestDox('extracts object without requirement using object prefix and splObjectId')]
    public function testExtractsObjectWithoutRequirement(): void
    {
        $obj = new \stdClass();

        $element = ContentElementBuilder::create('widget', 'elem-6')
            ->withProperty('payload', $obj)
            ->build();

        $this->visitor->enter($element);
        $this->visitor->leave($element);

        $data = $this->visitor->getData();
        $assignments = $this->visitor->getAssignments();

        $refId = $assignments['elem-6']['payload'];
        static::assertSame('object:' . spl_object_id($obj), $refId);
        static::assertSame($obj, $data[$refId]);
    }

    #[TestDox('stores identical Entity and config combinations as a single deduplicated entry')]
    public function testDeduplicates(): void
    {
        $entity = new StubExtractorEntity('shared-id');
        $config = new StubLoaderConfig();

        $this->configSerializerProvider->method('encode')
            ->willReturn(['type' => 'entity', 'id' => 'shared']);

        $element1 = ContentElementBuilder::create('card-1', 'elem-a')
            ->withProperty('product', $entity)
            ->withDataRequirement('product', 'entity', $config)
            ->build();

        $element2 = ContentElementBuilder::create('card-2', 'elem-b')
            ->withProperty('product', $entity)
            ->withDataRequirement('product', 'entity', $config)
            ->build();

        $this->visitor->enter($element1);
        $this->visitor->leave($element1);
        $this->visitor->enter($element2);
        $this->visitor->leave($element2);

        $assignments = $this->visitor->getAssignments();
        static::assertSame($assignments['elem-a']['product'], $assignments['elem-b']['product']);

        $data = $this->visitor->getData();
        static::assertCount(1, $data);
    }

    #[TestDox('stores a null scalar property under a scalar reference id, not deduplicated')]
    public function testExtractsNullScalarProperty(): void
    {
        $element = ContentElementBuilder::create('text', 'elem-9')
            ->withProperty('title', null)
            ->build();

        $this->visitor->enter($element);
        $this->visitor->leave($element);

        $data = $this->visitor->getData();
        $assignments = $this->visitor->getAssignments();

        $refId = $assignments['elem-9']['title'];
        static::assertStringStartsWith('scalar:', $refId);
        static::assertArrayHasKey($refId, $data);
        static::assertNull($data[$refId]);
    }

    #[TestDox('stores an empty array property under an array reference id, not deduplicated')]
    public function testExtractsEmptyArrayProperty(): void
    {
        $element = ContentElementBuilder::create('list', 'elem-10')
            ->withProperty('items', [])
            ->build();

        $this->visitor->enter($element);
        $this->visitor->leave($element);

        $data = $this->visitor->getData();
        $assignments = $this->visitor->getAssignments();

        $refId = $assignments['elem-10']['items'];
        static::assertStringStartsWith('array:', $refId);
        static::assertArrayHasKey($refId, $data);
        static::assertSame([], $data[$refId]);
    }

    #[TestDox('clears properties on element after extraction')]
    public function testClearsPropertiesOnElement(): void
    {
        $element = ContentElementBuilder::create('text', 'elem-7')
            ->withProperty('title', 'Hello')
            ->build();

        $this->visitor->enter($element);

        static::assertSame([], $element->getProperties());
    }

    #[TestDox('removes empty assignment entries during leave')]
    public function testLeaveRemovesEmptyAssignmentEntries(): void
    {
        $element = ContentElementBuilder::create('empty', 'elem-8')->build();

        $this->visitor->enter($element);
        $this->visitor->leave($element);

        $assignments = $this->visitor->getAssignments();
        static::assertArrayNotHasKey('elem-8', $assignments);
    }
}
