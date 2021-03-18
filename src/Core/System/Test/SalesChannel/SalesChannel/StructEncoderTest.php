<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Api\ResponseFields;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;

class StructEncoderTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var StructEncoder
     */
    private $encoder;

    protected function setUp(): void
    {
        $this->encoder = $this->getContainer()->get(StructEncoder::class);
    }

    public function testApiAliasIsSet(): void
    {
        $foo = new MyTestStruct('foo', 'bar');

        $encoded = $this->encoder->encode($foo, new ResponseFields([]));

        static::assertEquals(
            ['foo' => 'foo', 'bar' => 'bar', 'apiAlias' => 'test-struct'],
            $encoded
        );
    }

    public function testIncludesByApiAlias(): void
    {
        $foo = new MyTestStruct('foo', 'bar');

        $encoded = $this->encoder->encode($foo, new ResponseFields([
            'test-struct' => ['foo'],
        ]));

        static::assertEquals(
            ['foo' => 'foo', 'apiAlias' => 'test-struct'],
            $encoded
        );
    }

    public function testIncludesSupportsExtensions(): void
    {
        $foo = new MyTestStruct('foo', 'bar');
        $foo->addExtension('myExtension', new MyTestStruct('foo2', 'bar2'));

        $fields = new ResponseFields([
            'test-struct' => ['foo', 'myExtension'],
        ]);

        $encoded = $this->encoder->encode($foo, $fields);

        static::assertEquals(
            [
                'foo' => 'foo',
                'extensions' => [
                    'myExtension' => [
                        'foo' => 'foo2',
                        'apiAlias' => 'test-struct',
                    ],
                ],
                'apiAlias' => 'test-struct',
            ],
            $encoded
        );
    }

    public function testSupportsNullExtensions(): void
    {
        $foo = new MyTestStruct('foo', 'bar');
        $foo->addExtension('myExtension', null);

        $fields = new ResponseFields([
            'test-struct' => ['foo', 'myExtension'],
        ]);

        $encoded = $this->encoder->encode($foo, $fields);

        static::assertEquals(
            [
                'foo' => 'foo',
                'extensions' => [
                    'myExtension' => null,
                ],
                'apiAlias' => 'test-struct',
            ],
            $encoded
        );
    }

    public function testCollectionEncoding(): void
    {
        $collection = new StructCollection();
        $collection->add(new MyTestStruct(1, 1));
        $collection->add(new MyTestStruct(2, 2));
        $collection->add(new MyTestStruct(3, 3));

        $foo = new MyTestStruct('foo', $collection);

        $fields = new ResponseFields([
            'test-struct' => ['foo', 'bar'],
        ]);

        $encoded = $this->encoder->encode($foo, $fields);

        static::assertEquals(
            [
                'foo' => 'foo',
                'bar' => [
                    ['foo' => 1, 'bar' => 1, 'apiAlias' => 'test-struct'],
                    ['foo' => 2, 'bar' => 2, 'apiAlias' => 'test-struct'],
                    ['foo' => 3, 'bar' => 3, 'apiAlias' => 'test-struct'],
                ],
                'apiAlias' => 'test-struct',
            ],
            $encoded
        );
    }

    public function testNestedCollections(): void
    {
        $collection = new StructCollection();

        $nested = new StructCollection();
        $nested->add(new MyTestStruct('nested1'));
        $nested->add(new MyTestStruct('nested2'));
        $nested->add(new AnotherStruct('nested3'));

        $collection->add(new AnotherStruct('another1', $nested));
        $collection->add(new AnotherStruct('another2'));
        $collection->add(new MyTestStruct('myTest1'));

        $root = new MyTestStruct('root', $collection);

        $fields = new ResponseFields([
            'test-struct' => ['foo', 'bar'],
            'another-struct' => ['bar'],
        ]);

        $encoded = $this->encoder->encode($root, $fields);

        static::assertEquals(
            [
                'foo' => 'root',
                'bar' => [
                    [
                        'bar' => [
                            ['foo' => 'nested1', 'bar' => null, 'apiAlias' => 'test-struct'],
                            ['foo' => 'nested2', 'bar' => null, 'apiAlias' => 'test-struct'],
                            ['bar' => null, 'apiAlias' => 'another-struct'],
                        ],
                        'apiAlias' => 'another-struct',
                    ],
                    [
                        'bar' => null,
                        'apiAlias' => 'another-struct',
                    ],
                    [
                        'foo' => 'myTest1',
                        'bar' => null,
                        'apiAlias' => 'test-struct',
                    ],
                ],
                'apiAlias' => 'test-struct',
            ],
            $encoded
        );
    }

    public function testApiAwareForTranslatedFields(): void
    {
        $entity = new MyEntity();
        $entity->setId(Uuid::randomHex());
        $entity->setName('test');
        $entity->setDescription('test');
        $entity->setTranslated([
            'name' => 'test',
            'description' => 'test',
        ]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('has')
            ->willReturn(true);

        $definition = new MyEntityDefinition();
        $definition->compile(
            $this->getContainer()->get(DefinitionInstanceRegistry::class)
        );

        $registry->method('getByEntityName')
            ->willReturn($definition);

        $encoder = new StructEncoder(
            $registry,
            $this->getContainer()->get('serializer')
        );

        $encoded = $encoder->encode($entity, new ResponseFields(null));

        static::assertArrayHasKey('name', $encoded);
        static::assertArrayNotHasKey('description', $encoded);
        static::assertArrayHasKey('translated', $encoded);

        static::assertArrayHasKey('name', $encoded['translated']);
        static::assertArrayNotHasKey('description', $encoded['translated']);
    }
}

class MyTestStruct extends Struct
{
    public $foo;

    public $bar;

    public function __construct($foo = null, $bar = null)
    {
        $this->foo = $foo;
        $this->bar = $bar;
    }

    public function getApiAlias(): string
    {
        return 'test-struct';
    }
}

class AnotherStruct extends MyTestStruct
{
    public function getApiAlias(): string
    {
        return 'another-struct';
    }
}

class MyEntity extends Entity
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $description;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }
}

class MyEntityDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'my_entity';
    }

    public function getEntityClass(): string
    {
        return MyEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware()),
            (new TranslatedField('name'))->addFlags(new ApiAware()),
            new TranslatedField('description'),
        ]);
    }
}
