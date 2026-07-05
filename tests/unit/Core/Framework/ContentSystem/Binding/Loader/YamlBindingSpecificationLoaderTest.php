<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\BindingSpecificationSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeNameResolver;
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
    private const MINIMAL_VALID_YAML = "id: from-media-library\ntype: media-gallery\nlabel: \"From media library\"\n";

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

    #[TestDox('derives the id from the body and keeps the source label')]
    public function testLoadsSpecificationWithIdFromBody(): void
    {
        file_put_contents($this->tempDir . '/binding.yaml', self::MINIMAL_VALID_YAML);

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir)]);

        $specifications = $loader->load();

        static::assertCount(1, $specifications);
        static::assertSame('from-media-library', $specifications[0]->id());
        static::assertSame('media-gallery', $specifications[0]->type());
        static::assertSame('core', $specifications[0]->source());
    }

    #[TestDox('allows two different sources to each ship the same bare id without throwing')]
    public function testAllowsSameBareIdAcrossDifferentSources(): void
    {
        $dirA = $this->tempDir . '/a';
        $dirB = $this->tempDir . '/b';
        mkdir($dirA, 0777, true);
        mkdir($dirB, 0777, true);
        file_put_contents($dirA . '/binding.yaml', self::MINIMAL_VALID_YAML);
        file_put_contents($dirB . '/binding.yaml', self::MINIMAL_VALID_YAML);

        $loader = $this->createLoader([
            new BindingSpecificationSourceDirectory('source-a', $dirA),
            new BindingSpecificationSourceDirectory('source-b', $dirB),
        ]);

        $specifications = $loader->load();

        static::assertCount(2, $specifications);
        static::assertSame(['source-a', 'source-b'], array_map(static fn ($specification) => $specification->source(), $specifications));
    }

    #[TestDox('loads an id at exactly the maximum length of 255 characters')]
    public function testLoadsIdAtExactlyMaxLength(): void
    {
        // The boundary value at MAX_ID_LENGTH (255) must load; only a length greater than 255 is rejected.
        $id = str_repeat('a', 255);
        file_put_contents($this->tempDir . '/binding.yaml', "id: {$id}\ntype: media-gallery\nlabel: \"From media library\"\n");

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir)]);

        $specifications = $loader->load();

        static::assertCount(1, $specifications);
        static::assertSame($id, $specifications[0]->id());
    }

    #[TestDox('returns an empty array for a non-existent directory')]
    public function testReturnsEmptyForMissingDirectory(): void
    {
        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', '/path/does/not/exist')]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('returns an empty array for a directory that exists but holds no YAML files')]
    public function testReturnsEmptyForDirectoryWithoutYaml(): void
    {
        file_put_contents($this->tempDir . '/notes.txt', 'not a yaml file');

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir)]);

        static::assertSame([], $loader->load());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function throwsOnLoadFailureProvider(): iterable
    {
        yield 'missing id' => ['binding.yaml', "type: media-gallery\nlabel: \"From media library\"\n", 'missing or empty "id"'];
        yield 'blank id' => ['binding.yaml', "id: \"\"\ntype: media-gallery\nlabel: \"From media library\"\n", 'missing or empty "id"'];
        yield 'id exceeds max length' => ['binding.yaml', 'id: ' . str_repeat('a', 256) . "\ntype: media-gallery\nlabel: \"From media library\"\n", 'id exceeds the maximum length of 255 characters'];
        yield 'scalar body' => ['scalar.yaml', 'just a string', 'YAML file must contain an array/map, got string'];
    }

    #[DataProvider('throwsOnLoadFailureProvider')]
    #[TestDox('throws bindingSpecificationLoadFailed for $_dataName')]
    public function testThrowsOnLoadFailure(string $filename, string $body, string $reason): void
    {
        file_put_contents($this->tempDir . '/' . $filename, $body);

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir)]);

        $this->expectExceptionObject(
            ContentSystemException::bindingSpecificationLoadFailed($this->tempDir . '/' . $filename, $reason)
        );

        $loader->load();
    }

    #[TestDox('throws on a within-source duplicate id, naming both files')]
    public function testThrowsOnWithinSourceDuplicateId(): void
    {
        file_put_contents($this->tempDir . '/a.yaml', self::MINIMAL_VALID_YAML);
        file_put_contents($this->tempDir . '/b.yaml', self::MINIMAL_VALID_YAML);

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir)]);

        // Filesystem iteration order is not guaranteed, so assert the duplicate is rejected and names both
        // files without coupling to which file is seen first.
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/Binding specification "from-media-library" is already registered by "[ab]\.yaml", cannot register again from "[ab]\.yaml"/');

        $loader->load();
    }

    #[TestDox('throws when the same source ships the same id across two directories')]
    public function testThrowsOnSameSourceAcrossDifferentDirectories(): void
    {
        $dirA = $this->tempDir . '/a';
        $dirB = $this->tempDir . '/b';
        mkdir($dirA, 0777, true);
        mkdir($dirB, 0777, true);
        file_put_contents($dirA . '/binding.yaml', self::MINIMAL_VALID_YAML);
        file_put_contents($dirB . '/binding.yaml', self::MINIMAL_VALID_YAML);

        $loader = $this->createLoader([
            new BindingSpecificationSourceDirectory('source-a', $dirA),
            new BindingSpecificationSourceDirectory('source-a', $dirB),
        ]);

        $this->expectExceptionObject(
            ContentSystemException::bindingSpecificationDuplicate('from-media-library', 'source-a', 'source-a')
        );

        $loader->load();
    }

    #[TestDox('fails hard on unparsable YAML, surfacing the file path')]
    public function testFailsOnUnparsableYaml(): void
    {
        file_put_contents($this->tempDir . '/broken.yaml', "id: \"unterminated\n  bad: [");

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir)]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/Invalid YAML syntax/');
        $this->expectExceptionMessageMatches('/' . preg_quote($this->tempDir . '/broken.yaml', '/') . '/');

        $loader->load();
    }

    #[TestDox('throws bindingSpecificationsInvalid, surfacing the violation path, when the validator reports a problem')]
    public function testFailsValidationForMalformedSpecification(): void
    {
        // The spec carries a registered type and no sugared resolves, so canonicalization is a no-op; the
        // stubbed validator supplies the violation the loader must surface (the real constraint that produces
        // it is covered by the validator's own tests).
        file_put_contents($this->tempDir . '/broken.yaml', "id: broken\ntype: media-gallery\n");

        // Stub the validator to report one violation: this tests the loader's throw-on-violations wiring
        // (it surfaces the violation path via bindingSpecificationsInvalid). The real constraint that would
        // produce such a violation is covered by the validator's own tests.
        $failing = static::createStub(ValidatorInterface::class);
        $failing->method('validate')->willReturn(new ConstraintViolationList([
            new ConstraintViolation('resolves entry "image" must declare a non-blank "loader"', null, [], null, 'bindings[broken].resolves[image].loader', null),
        ]));

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir)], $failing);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/bindings\[broken\]\.resolves\[image\]\.loader/');

        $loader->load();
    }

    #[TestDox('loads an inline binding whose implicit type is resolved from the file path and directory prefix')]
    public function testLoadsInlineBindingWithImplicitTypeFromPathAndPrefix(): void
    {
        // A file media/image.yaml under prefix "Sw" yields type "Sw:Media:Image" (ElementTypeNameResolver's
        // kebab-to-PascalCase, colon-joined, prefixed rule).
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "meta:\n  label: Image\nbindings:\n  from-media-library:\n    label: \"From media library\"\n");

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir, 'Sw')]);

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

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir, 'Sw')]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('rejects an inline entry that declares an explicit type')]
    public function testRejectsInlineEntryWithExplicitType(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  from-media-library:\n    type: Sw:Media:Image\n    label: x\n");

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir, 'Sw')]);

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

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir, 'Sw')]);

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationCanonicalizationFailed(
            'from-media-library',
            'an inline binding entry must not declare "id"; the map key is the id.',
        ));

        $loader->load();
    }

    #[TestDox('throws on two inline entries sharing an id across two type files of one directory')]
    public function testThrowsOnDuplicateInlineIdAcrossTwoTypeFiles(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        mkdir($this->tempDir . '/hero', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings:\n  shared:\n    label: a\n");
        file_put_contents($this->tempDir . '/hero/banner.yaml', "bindings:\n  shared:\n    label: b\n");

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir, 'Sw')]);

        // Filesystem iteration order is not guaranteed; assert the duplicate is rejected naming both files
        // without coupling to which is seen first.
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/Binding specification "shared" is already registered by "(image|banner)\.yaml", cannot register again from "(image|banner)\.yaml"/');

        $loader->load();
    }

    #[TestDox('throws when an inline entry and a standalone file share a bare id in the same source')]
    public function testThrowsOnInlineAndStandaloneSameIdInSameSource(): void
    {
        $standalone = $this->tempDir . '/standalone';
        $types = $this->tempDir . '/types';
        mkdir($standalone, 0777, true);
        mkdir($types . '/media', 0777, true);
        file_put_contents($standalone . '/dup.yaml', "id: dup\ntype: media-gallery\nlabel: standalone\n");
        file_put_contents($types . '/media/image.yaml', "bindings:\n  dup:\n    label: inline\n");

        $loader = $this->createLoader([
            new BindingSpecificationSourceDirectory('core', $standalone),
            new BindingSpecificationSourceDirectory('core', $types, 'Sw'),
        ]);

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationDuplicate('dup', 'core', 'core'));

        $loader->load();
    }

    #[TestDox('loads an inline entry and a standalone file sharing a bare id under different sources')]
    public function testLoadsInlineAndStandaloneSameIdInDifferentSources(): void
    {
        $standalone = $this->tempDir . '/standalone';
        $types = $this->tempDir . '/types';
        mkdir($standalone, 0777, true);
        mkdir($types . '/media', 0777, true);
        file_put_contents($standalone . '/shared.yaml', "id: shared\ntype: media-gallery\nlabel: standalone\n");
        file_put_contents($types . '/media/image.yaml', "bindings:\n  shared:\n    label: inline\n");

        $loader = $this->createLoader([
            new BindingSpecificationSourceDirectory('source-a', $standalone),
            new BindingSpecificationSourceDirectory('source-b', $types, 'Sw'),
        ]);

        $specifications = $loader->load();

        static::assertCount(2, $specifications);
        static::assertSame(['shared', 'shared'], array_map(static fn ($specification) => $specification->id(), $specifications));
        static::assertSame(['source-a', 'source-b'], array_map(static fn ($specification) => $specification->source(), $specifications));
    }

    #[TestDox('fails hard when the bindings section is not a map, naming the file')]
    public function testFailsOnNonMapBindingsSection(): void
    {
        mkdir($this->tempDir . '/media', 0777, true);
        file_put_contents($this->tempDir . '/media/image.yaml', "bindings: not-a-map\n");

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir, 'Sw')]);

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

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir, 'Sw')]);

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadFailed(
            $this->tempDir . '/media/image.yaml',
            'the "bindings" entry "from-media-library" must be a map, got string',
        ));

        $loader->load();
    }

    #[TestDox('throws bindingSpecificationPromotedDuplicate when a standalone and an inline spec both promote one type across two sources')]
    public function testThrowsOnTwoPromotedSpecificationsForOneType(): void
    {
        $standalone = $this->tempDir . '/standalone';
        $types = $this->tempDir . '/types';
        mkdir($standalone, 0777, true);
        mkdir($types . '/media', 0777, true);
        file_put_contents($standalone . '/promoted.yaml', "id: promoted-standalone\ntype: Sw:Media:Image\nlabel: standalone\npromoted: true\n");
        file_put_contents($types . '/media/image.yaml', "bindings:\n  promoted-inline:\n    label: inline\n    promoted: true\n");

        $loader = $this->createLoader([
            new BindingSpecificationSourceDirectory('source-a', $standalone),
            new BindingSpecificationSourceDirectory('source-b', $types, 'Sw'),
        ]);

        // The standalone directory is scanned first, so its qualified id is the incumbent promoted flag.
        $this->expectExceptionObject(ContentSystemException::bindingSpecificationPromotedDuplicate(
            'Sw:Media:Image',
            'source-a:promoted-standalone',
            'source-b:promoted-inline',
        ));

        $loader->load();
    }

    #[TestDox('loads two promoted specifications for DIFFERENT types without throwing')]
    public function testLoadsTwoPromotedSpecificationsForDifferentTypes(): void
    {
        $standalone = $this->tempDir . '/standalone';
        $types = $this->tempDir . '/types';
        mkdir($standalone, 0777, true);
        mkdir($types . '/media', 0777, true);
        file_put_contents($standalone . '/promoted.yaml', "id: promoted-standalone\ntype: media-gallery\nlabel: standalone\npromoted: true\n");
        file_put_contents($types . '/media/image.yaml', "bindings:\n  promoted-inline:\n    label: inline\n    promoted: true\n");

        $loader = $this->createLoader([
            new BindingSpecificationSourceDirectory('source-a', $standalone),
            new BindingSpecificationSourceDirectory('source-b', $types, 'Sw'),
        ]);

        $specifications = $loader->load();

        static::assertCount(2, $specifications);
        static::assertSame([true, true], array_map(static fn ($specification) => $specification->isPromoted(), $specifications));
        static::assertSame(['media-gallery', 'Sw:Media:Image'], array_map(static fn ($specification) => $specification->type(), $specifications));
    }

    /**
     * @param list<BindingSpecificationSourceDirectory> $directories
     */
    private function createLoader(array $directories, ?ValidatorInterface $validator = null): YamlBindingSpecificationLoader
    {
        // The loader validates the decoded DTO collection through its injected validator. These tests cover
        // loading MECHANICS (id-from-body, dedup, file handling), not the constraints themselves, so a stub
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
        // These tests cover loading MECHANICS (id-from-body, dedup, file handling), not canonicalization (which
        // has its own dedicated test): the type registry accepts every type and the fixtures carry no sugared
        // resolves, so the map resolver and DAL registries are never reached during the load.
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
