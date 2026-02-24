<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Layout\Element\Visitor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Content\ContentSystem\Layout\Element\Visitor\PropertiesExtractionVisitor;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\StubExtractorEntity;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\StubLoaderConfig;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\StubStruct;

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
        $this->visitor = new PropertiesExtractionVisitor($this->configSerializerProvider);
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

    #[TestDox('extracts Entity object with requirement using apiAlias, uniqueIdentifier, and config hash')]
    public function testExtractsEntity(): void
    {
        $entity = new StubExtractorEntity('entity-abc');
        $config = new StubLoaderConfig();

        $this->configSerializerProvider->method('encode')
            ->willReturn(['type' => 'entity', 'id' => 'abc']);

        $element = ContentElementBuilder::create('card', 'elem-3')
            ->withProperty('product', $entity)
            ->withDataRequirement('product', 'entity', $config)
            ->build();

        $this->visitor->enter($element);
        $this->visitor->leave($element);

        $data = $this->visitor->getData();
        $assignments = $this->visitor->getAssignments();

        $refId = $assignments['elem-3']['product'];
        static::assertStringStartsWith('test_entity:entity-abc:', $refId);
        static::assertSame($entity, $data[$refId]);
    }

    #[TestDox('extracts Struct object with requirement using apiAlias, splObjectId, and config hash')]
    public function testExtractsStruct(): void
    {
        $struct = new StubStruct();
        $config = new StubLoaderConfig();

        $this->configSerializerProvider->method('encode')
            ->willReturn(['type' => 'struct']);

        $element = ContentElementBuilder::create('widget', 'elem-4')
            ->withProperty('data', $struct)
            ->withDataRequirement('data', 'custom', $config)
            ->build();

        $this->visitor->enter($element);
        $this->visitor->leave($element);

        $data = $this->visitor->getData();
        $assignments = $this->visitor->getAssignments();

        $refId = $assignments['elem-4']['data'];
        static::assertStringStartsWith('test_struct:', $refId);
        static::assertSame($struct, $data[$refId]);
    }

    #[TestDox('extracts plain object with requirement using object prefix, splObjectId, and config hash')]
    public function testExtractsPlainObject(): void
    {
        $obj = new \stdClass();
        $config = new StubLoaderConfig();

        $this->configSerializerProvider->method('encode')
            ->willReturn(['type' => 'plain']);

        $element = ContentElementBuilder::create('widget', 'elem-5')
            ->withProperty('payload', $obj)
            ->withDataRequirement('payload', 'custom', $config)
            ->build();

        $this->visitor->enter($element);
        $this->visitor->leave($element);

        $data = $this->visitor->getData();
        $assignments = $this->visitor->getAssignments();

        $refId = $assignments['elem-5']['payload'];
        static::assertStringStartsWith('object:', $refId);
        static::assertSame($obj, $data[$refId]);
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

    #[TestDox('deduplicates elements with same entity and config containing nested associative sub-arrays')]
    public function testDeduplicatesWithNestedAssociativeConfigArray(): void
    {
        $entity = new StubExtractorEntity('entity-nested');
        $config = new StubLoaderConfig();

        // The encoded config contains both a nested associative sub-array (triggers
        // recursive canonicalizeConfig) and a list array (triggers sort branch).
        $this->configSerializerProvider->method('encode')
            ->willReturn([
                'filters' => ['limit' => 10, 'status' => 'active'],
                'associations' => ['media', 'manufacturer'],
                'type' => 'entity',
            ]);

        $element1 = ContentElementBuilder::create('card', 'elem-nested-1')
            ->withProperty('product', $entity)
            ->withDataRequirement('product', 'entity', $config)
            ->build();

        $element2 = ContentElementBuilder::create('card', 'elem-nested-2')
            ->withProperty('product', $entity)
            ->withDataRequirement('product', 'entity', $config)
            ->build();

        $this->visitor->enter($element1);
        $this->visitor->leave($element1);
        $this->visitor->enter($element2);
        $this->visitor->leave($element2);

        $assignments = $this->visitor->getAssignments();
        static::assertSame($assignments['elem-nested-1']['product'], $assignments['elem-nested-2']['product']);

        $data = $this->visitor->getData();
        static::assertCount(1, $data);
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
