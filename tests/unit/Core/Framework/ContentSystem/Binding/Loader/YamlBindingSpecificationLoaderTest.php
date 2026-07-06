<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeNameResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(YamlBindingSpecificationLoader::class)]
class YamlBindingSpecificationLoaderTest extends TestCase
{
    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yaml-binding-specification-loader-test-' . getmypid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    #[TestDox('loads an inline binding whose implicit type is resolved from the file path and directory prefix')]
    public function testLoadsInlineBindingWithImplicitTypeFromPathAndPrefix(): void
    {
        // A file media/image.yaml under prefix "Sw" yields type "Sw:Media:Image" (ElementTypeNameResolver's
        // kebab-to-PascalCase, colon-joined, prefixed rule).
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "meta:\n  label: Image\nbindings:\n  from-media-library:\n    label: \"From media library\"\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $specifications = $loader->load();

        static::assertCount(1, $specifications);
        static::assertSame('from-media-library', $specifications[0]->id());
        static::assertSame('Sw:Media:Image', $specifications[0]->type());
        static::assertSame('core', $specifications[0]->source());
    }

    #[TestDox('skips an element-type file that carries no bindings section')]
    public function testSkipsTypeFileWithoutBindingsSection(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "meta:\n  label: Image\nproperties:\n  mediaId:\n    type: string\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('returns an empty array for a non-existent directory')]
    public function testReturnsEmptyForMissingDirectory(): void
    {
        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', '/path/does/not/exist', 'Sw')]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('returns an empty array for a directory that exists but holds no YAML files')]
    public function testReturnsEmptyForDirectoryWithoutYaml(): void
    {
        file_put_contents($this->tempDir . '/notes.txt', 'not a yaml file');

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('loads an inline id at exactly the maximum length of 255 characters')]
    public function testLoadsIdAtExactlyMaxLength(): void
    {
        $id = str_repeat('a', 255);
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  {$id}:\n    label: x\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $specifications = $loader->load();

        static::assertCount(1, $specifications);
        static::assertSame($id, $specifications[0]->id());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function throwsOnLoadFailureProvider(): iterable
    {
        yield 'blank id' => ['media/image.yaml', "bindings:\n  \"\":\n    label: x\n", 'missing or empty "id"'];
        // A numeric YAML key decodes to a PHP int map key, so assertValidId sees a non-string id.
        yield 'non-string id' => ['media/image.yaml', "bindings:\n  123:\n    label: x\n", 'missing or empty "id"'];
        yield 'id exceeds max length' => ['media/image.yaml', "bindings:\n  " . str_repeat('a', 256) . ":\n    label: x\n", 'id exceeds the maximum length of 255 characters'];
        yield 'scalar body' => ['media/scalar.yaml', 'just a string', 'YAML file must contain an array/map, got string'];
    }

    #[DataProvider('throwsOnLoadFailureProvider')]
    #[TestDox('throws bindingSpecificationLoadFailed for $_dataName')]
    public function testThrowsOnLoadFailure(string $relativePath, string $body, string $reason): void
    {
        $fullPath = $this->tempDir . '/' . $relativePath;
        mkdir(\dirname($fullPath), 0777, true);
        file_put_contents($fullPath, $body);

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $this->expectExceptionObject(
            ContentSystemException::bindingSpecificationLoadFailed($fullPath, $reason)
        );

        $loader->load();
    }

    #[TestDox('throws on two inline entries sharing an id across two type files of one directory')]
    public function testThrowsOnDuplicateInlineIdAcrossTwoTypeFiles(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        mkdir($this->tempDir . '/hero', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  shared:\n    label: a\n");
        file_put_contents($this->tempDir . '/hero/banner.yaml', "bindings:\n  shared:\n    label: b\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        // Filesystem iteration order is not guaranteed; assert the duplicate is rejected naming both files
        // without coupling to which is seen first.
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/Binding specification "shared" is already registered by "(image|banner)\.yaml", cannot register again from "(image|banner)\.yaml"/');

        $loader->load();
    }

    #[TestDox('throws when the same source ships the same id across two directories')]
    public function testThrowsOnSameSourceAcrossDifferentDirectories(): void
    {
        $dirA = $this->tempDir . '/a';
        $dirB = $this->tempDir . '/b';
        mkdir($dirA . '/media', 0777, true);
        mkdir($dirB . '/hero', 0777, true);
        file_put_contents($dirA . '/media/image.yaml', "bindings:\n  shared:\n    label: a\n");
        file_put_contents($dirB . '/hero/banner.yaml', "bindings:\n  shared:\n    label: b\n");

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source-a', $dirA, 'Sw'),
            new ElementTypeSourceDirectory('source-a', $dirB, 'Sw'),
        ]);

        $this->expectExceptionObject(
            ContentSystemException::bindingSpecificationDuplicate('shared', 'source-a', 'source-a')
        );

        $loader->load();
    }

    #[TestDox('allows two different sources to each ship the same bare id without throwing')]
    public function testAllowsSameBareIdAcrossDifferentSources(): void
    {
        $dirA = $this->tempDir . '/a';
        $dirB = $this->tempDir . '/b';
        mkdir($dirA . '/media', 0777, true);
        mkdir($dirB . '/media', 0777, true);
        file_put_contents($dirA . '/media/image.yaml', "bindings:\n  shared:\n    label: a\n");
        file_put_contents($dirB . '/media/image.yaml', "bindings:\n  shared:\n    label: b\n");

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source-a', $dirA, 'Sw'),
            new ElementTypeSourceDirectory('source-b', $dirB, 'Sw'),
        ]);

        $specifications = $loader->load();

        static::assertCount(2, $specifications);
        static::assertSame(['source-a', 'source-b'], array_map(static fn ($specification) => $specification->source(), $specifications));
    }

    #[TestDox('rejects an inline entry that declares an explicit type')]
    public function testRejectsInlineEntryWithExplicitType(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  from-media-library:\n    type: Sw:Media:Image\n    label: x\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationCanonicalizationFailed(
            'from-media-library',
            'an inline binding entry must not declare "type"; the type is implicit from the containing element-type file.',
        ));

        $loader->load();
    }

    #[TestDox('rejects an inline entry that declares an explicit id')]
    public function testRejectsInlineEntryWithExplicitId(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  from-media-library:\n    id: something-else\n    label: x\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationCanonicalizationFailed(
            'from-media-library',
            'an inline binding entry must not declare "id"; the map key is the id.',
        ));

        $loader->load();
    }

    #[TestDox('fails hard when the bindings section is not a map, naming the file')]
    public function testFailsOnNonMapBindingsSection(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings: not-a-map\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadFailed(
            $this->tempDir . '/media/image.yaml',
            'the "bindings" section must be a map of specification id to entry, got string',
        ));

        $loader->load();
    }

    #[TestDox('fails hard when an inline entry is not a map, naming the file')]
    public function testFailsOnNonMapInlineEntry(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  from-media-library: not-a-map\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadFailed(
            $this->tempDir . '/media/image.yaml',
            'the "bindings" entry "from-media-library" must be a map, got string',
        ));

        $loader->load();
    }

    #[TestDox('fails hard on unparsable YAML, surfacing the file path')]
    public function testFailsOnUnparsableYaml(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/broken.yaml', "id: \"unterminated\n  bad: [");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/Invalid YAML syntax/');
        $this->expectExceptionMessageMatches('/' . preg_quote($this->tempDir . '/media/broken.yaml', '/') . '/');

        $loader->load();
    }

    #[TestDox('throws bindingSpecificationsInvalid, surfacing the violation path, when the validator reports a problem')]
    public function testFailsValidationForMalformedSpecification(): void
    {
        // The implicit type is registered (stub) and the entry carries no sugared resolves, so canonicalization
        // is a no-op.
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  broken:\n    label: x\n");

        // Stub the validator to report one violation: this tests the loader's throw-on-violations wiring
        // (it surfaces the violation path via bindingSpecificationsInvalid). The real constraint that would
        // produce such a violation is covered by the validator's own tests.
        $failing = static::createStub(ValidatorInterface::class);
        $failing->method('validate')->willReturn(new ConstraintViolationList([
            new ConstraintViolation('resolves entry "image" must declare a non-blank "loader"', null, [], null, 'bindings[broken].resolves[image].loader', null),
        ]));

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')], $failing);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/bindings\[broken\]\.resolves\[image\]\.loader/');

        $loader->load();
    }

    #[TestDox('throws bindingSpecificationPromotedDuplicate when two inline specs promote one type')]
    public function testThrowsOnTwoPromotedSpecificationsForOneType(): void
    {
        // Two entries in one type file share that file's implicit type Sw:Media:Image; both promoting it is an
        // authored bug the loader rejects hard.
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents(
            $this->tempDir . '/media/image.yaml',
            "bindings:\n  promoted-first:\n    label: first\n    promoted: true\n  promoted-second:\n    label: second\n    promoted: true\n",
        );

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        // The map preserves insertion order, so the first entry holds the incumbent promoted flag.
        $this->expectExceptionObject(ContentSystemException::bindingSpecificationPromotedDuplicate(
            'Sw:Media:Image',
            'core:promoted-first',
            'core:promoted-second',
        ));

        $loader->load();
    }

    #[TestDox('throws bindingSpecificationPromotedDuplicate when two specifications across two directories promote one type')]
    public function testThrowsOnTwoPromotedSpecificationsAcrossDirectories(): void
    {
        // Promoted uniqueness is accumulated across ALL of the loader's directories and sources, not per
        // directory: two directories each shipping ONE promoted specification for the same implicit type
        // Sw:Media:Image is still a duplicate the loader rejects hard.
        $dirA = $this->tempDir . '/source-a';
        $dirB = $this->tempDir . '/source-b';
        mkdir($dirA . '/media', 0777, true);
        mkdir($dirB . '/media', 0777, true);
        file_put_contents($dirA . '/media/image.yaml', "bindings:\n  promoted-a:\n    label: a\n    promoted: true\n");
        file_put_contents($dirB . '/media/image.yaml', "bindings:\n  promoted-b:\n    label: b\n    promoted: true\n");

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source-a', $dirA, 'Sw'),
            new ElementTypeSourceDirectory('source-b', $dirB, 'Sw'),
        ]);

        // Directory order determines the incumbent: source-a is scanned first, so it holds the promoted flag.
        $this->expectExceptionObject(ContentSystemException::bindingSpecificationPromotedDuplicate(
            'Sw:Media:Image',
            'source-a:promoted-a',
            'source-b:promoted-b',
        ));

        $loader->load();
    }

    #[TestDox('loads two promoted specifications for DIFFERENT types without throwing')]
    public function testLoadsTwoPromotedSpecificationsForDifferentTypes(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        mkdir($this->tempDir . '/hero', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  promoted-image:\n    label: image\n    promoted: true\n");
        file_put_contents($this->tempDir . '/hero/banner.yaml', "bindings:\n  promoted-banner:\n    label: banner\n    promoted: true\n");

        $loader = $this->createLoader([new ElementTypeSourceDirectory('core', $this->tempDir, 'Sw')]);

        $specifications = $loader->load();

        static::assertCount(2, $specifications);
        static::assertSame([true, true], array_map(static fn ($specification) => $specification->isPromoted(), $specifications));
        static::assertEqualsCanonicalizing(
            ['Sw:Media:Image', 'Sw:Hero:Banner'],
            array_map(static fn ($specification) => $specification->type(), $specifications),
        );
    }

    /**
     * @param list<ElementTypeSourceDirectory> $directories
     */
    private function createLoader(array $directories, ?ValidatorInterface $validator = null): YamlBindingSpecificationLoader
    {
        // The loader validates the decoded DTO collection through its injected validator. These tests cover
        // loading MECHANICS (implicit type, dedup, file handling), not the constraints themselves, so a stub
        // validator is injected: it sidesteps the collection's dep-injected TypeConsistentBindingSpecification
        // (whose validator the default no-arg factory cannot build) and the fixtures' unregistered types. The real
        // structural and semantic validation is covered by their own dedicated tests.
        return new YamlBindingSpecificationLoader(
            $directories,
            new BindingSpecificationSerializer(),
            $this->canonicalizer(),
            $validator ?? $this->passingValidator(),
            new ElementTypeNameResolver(),
        );
    }

    private function canonicalizer(): BindingSpecificationCanonicalizer
    {
        // The type registry accepts every type and the fixtures carry no sugared resolves, so the map resolver
        // and DAL registries are never reached during the load (canonicalization has its own dedicated test).
        $typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $typeRegistry->method('has')->willReturn(true);
        $typeRegistry->method('get')->willReturn(new ContentSystemElementTypeSpecification(
            'media-gallery',
            'Media gallery',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            [],
            [],
        ));

        $mapResolver = static::createStub(AbstractContentSystemDataLoaderMapResolver::class);
        $mapResolver->method('resolve')->willReturn(new ContentSystemDataLoaderMap([], []));

        return new BindingSpecificationCanonicalizer(
            $typeRegistry,
            $mapResolver,
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(SalesChannelDefinitionInstanceRegistry::class),
        );
    }

    private function passingValidator(): ValidatorInterface
    {
        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        return $validator;
    }
}
