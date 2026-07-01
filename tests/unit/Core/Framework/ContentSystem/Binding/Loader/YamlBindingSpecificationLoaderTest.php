<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\BindingSpecificationSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
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

    #[TestDox('throws when the body is missing an id')]
    public function testThrowsWhenIdIsMissing(): void
    {
        file_put_contents($this->tempDir . '/binding.yaml', "type: media-gallery\nlabel: \"From media library\"\n");

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir)]);

        $this->expectExceptionObject(
            ContentSystemException::bindingSpecificationLoadFailed(
                $this->tempDir . '/binding.yaml',
                'missing or empty "id"'
            )
        );

        $loader->load();
    }

    #[TestDox('throws when the body declares a blank id')]
    public function testThrowsWhenIdIsBlank(): void
    {
        file_put_contents($this->tempDir . '/binding.yaml', "id: \"\"\ntype: media-gallery\nlabel: \"From media library\"\n");

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir)]);

        $this->expectExceptionObject(
            ContentSystemException::bindingSpecificationLoadFailed(
                $this->tempDir . '/binding.yaml',
                'missing or empty "id"'
            )
        );

        $loader->load();
    }

    #[TestDox('throws when the body declares an id longer than the persisted name column allows')]
    public function testThrowsWhenIdExceedsMaxLength(): void
    {
        $id = str_repeat('a', 256);
        file_put_contents($this->tempDir . '/binding.yaml', "id: {$id}\ntype: media-gallery\nlabel: \"From media library\"\n");

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir)]);

        $this->expectExceptionObject(
            ContentSystemException::bindingSpecificationLoadFailed(
                $this->tempDir . '/binding.yaml',
                'id exceeds the maximum length of 255 characters'
            )
        );

        $loader->load();
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

    #[TestDox('fails hard when the file body is a scalar rather than a map')]
    public function testFailsOnScalarBody(): void
    {
        file_put_contents($this->tempDir . '/scalar.yaml', 'just a string');

        $loader = $this->createLoader([new BindingSpecificationSourceDirectory('core', $this->tempDir)]);

        $this->expectExceptionObject(
            ContentSystemException::bindingSpecificationLoadFailed(
                $this->tempDir . '/scalar.yaml',
                'YAML file must contain an array/map, got string'
            )
        );

        $loader->load();
    }

    #[TestDox('throws bindingSpecificationsInvalid, surfacing the violation path, when the validator reports a problem')]
    public function testFailsValidationForMalformedSpecification(): void
    {
        file_put_contents($this->tempDir . '/broken.yaml', "id: broken\nresolves:\n  image:\n    config: []\n");

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

    /**
     * @param list<BindingSpecificationSourceDirectory> $directories
     */
    private function createLoader(array $directories, ?ValidatorInterface $validator = null): YamlBindingSpecificationLoader
    {
        // The loader validates the decoded DTO collection through its injected validator. These tests cover
        // loading MECHANICS (id-from-body, dedup, file handling), not the constraints themselves, so a stub
        // validator is injected: it sidesteps the DTO's dep-injected TypeConsistentBindingSpecification (whose
        // validator the default no-arg factory cannot build) and the fixtures' unregistered types. The real
        // structural and semantic validation is covered by their own dedicated tests.
        return new YamlBindingSpecificationLoader(
            $directories,
            new BindingSpecificationSerializer(),
            $validator ?? $this->passingValidator(),
        );
    }

    private function passingValidator(): ValidatorInterface
    {
        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        return $validator;
    }
}
