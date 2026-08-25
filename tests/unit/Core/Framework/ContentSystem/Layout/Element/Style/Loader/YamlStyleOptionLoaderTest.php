<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\StyleOptionSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(YamlStyleOptionLoader::class)]
class YamlStyleOptionLoaderTest extends TestCase
{
    private const MINIMAL_VALID_YAML = "type: boolean\n";

    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yaml-style-option-loader-test-' . getmypid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    #[TestDox('derives the wire-key name from the filename and keeps the source label')]
    public function testLoadsOptionsWithNameFromFilename(): void
    {
        file_put_contents($this->tempDir . '/col-span.yaml', "type: integer\nrange:\n  min: 1\n  max: 12\n");

        $loader = $this->createLoader([new StyleOptionSourceDirectory('core', $this->tempDir)]);

        $options = $loader->load();

        static::assertCount(1, $options);
        static::assertSame('col-span', $options[0]->name());
        static::assertSame('core', $options[0]->source());
        static::assertSame('integer', $options[0]->valueType()->type());
    }

    #[TestDox('validates all shipped core style option definitions against declaration constraints')]
    public function testShippedCoreDefinitionsValidate(): void
    {
        // An out-of-bounds advisory default in a shipped core definition now fails at load.
        $coreDir = \dirname((string) (new \ReflectionClass(ElementStyle::class))->getFileName()) . '/Definitions';

        $options = $this->createLoader([new StyleOptionSourceDirectory('core', $coreDir)])->load();

        static::assertCount(5, $options);
    }

    #[TestDox('returns an empty array for a non-existent directory')]
    public function testReturnsEmptyForMissingDirectory(): void
    {
        $loader = $this->createLoader([new StyleOptionSourceDirectory('core', '/path/does/not/exist')]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('returns an empty array for a directory that exists but holds no YAML files')]
    public function testReturnsEmptyForDirectoryWithoutYaml(): void
    {
        file_put_contents($this->tempDir . '/notes.txt', 'not a yaml file');

        $loader = $this->createLoader([new StyleOptionSourceDirectory('core', $this->tempDir)]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('rejects a filename that is not a valid kebab-case wire key')]
    public function testRejectsInvalidFilename(): void
    {
        file_put_contents($this->tempDir . '/Col_Span.yaml', self::MINIMAL_VALID_YAML);

        $loader = $this->createLoader([new StyleOptionSourceDirectory('core', $this->tempDir)]);

        $this->expectExceptionObject(ContentSystemException::styleOptionInvalidFilename('Col_Span', 'Col_Span.yaml'));

        $loader->load();
    }

    #[TestDox('rejects an all-numeric filename that would coerce to an int array key on read')]
    public function testRejectsAllNumericFilename(): void
    {
        file_put_contents($this->tempDir . '/123.yaml', self::MINIMAL_VALID_YAML);

        $loader = $this->createLoader([new StyleOptionSourceDirectory('core', $this->tempDir)]);

        $this->expectExceptionObject(ContentSystemException::styleOptionInvalidFilename('123', '123.yaml'));

        $loader->load();
    }

    #[TestDox('fails hard on a cross-directory duplicate option name, naming both sources')]
    public function testFailsOnCrossDirectoryDuplicate(): void
    {
        $dirA = $this->tempDir . '/a';
        $dirB = $this->tempDir . '/b';
        mkdir($dirA, 0777, true);
        mkdir($dirB, 0777, true);
        file_put_contents($dirA . '/col-span.yaml', self::MINIMAL_VALID_YAML);
        file_put_contents($dirB . '/col-span.yaml', self::MINIMAL_VALID_YAML);

        $loader = $this->createLoader([
            new StyleOptionSourceDirectory('source-a', $dirA),
            new StyleOptionSourceDirectory('source-b', $dirB),
        ]);

        $this->expectExceptionObject(ContentSystemException::styleOptionDuplicate('col-span', 'source-a', 'source-b'));

        $loader->load();
    }

    #[TestDox('fails hard when yaml and yml files in one directory resolve to the same name')]
    public function testFailsOnWithinDirectoryDuplicate(): void
    {
        file_put_contents($this->tempDir . '/col-span.yaml', self::MINIMAL_VALID_YAML);
        file_put_contents($this->tempDir . '/col-span.yml', self::MINIMAL_VALID_YAML);

        $loader = $this->createLoader([new StyleOptionSourceDirectory('core', $this->tempDir)]);

        $this->expectExceptionObject(ContentSystemException::styleOptionDuplicate('col-span', 'col-span.yaml', 'col-span.yml'));

        $loader->load();
    }

    #[TestDox('fails hard on unparsable YAML, surfacing the file path')]
    public function testFailsOnUnparsableYaml(): void
    {
        file_put_contents($this->tempDir . '/broken.yaml', "type: \"unterminated\n  bad: [");

        $loader = $this->createLoader([new StyleOptionSourceDirectory('core', $this->tempDir)]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($this->tempDir . '/broken.yaml', '/') . '/');

        $loader->load();
    }

    #[TestDox('fails hard when the file body is a scalar rather than a map')]
    public function testFailsOnScalarBody(): void
    {
        file_put_contents($this->tempDir . '/scalar.yaml', 'just a string');

        $loader = $this->createLoader([new StyleOptionSourceDirectory('core', $this->tempDir)]);

        $this->expectExceptionObject(
            ContentSystemException::styleOptionLoadFailed(
                $this->tempDir . '/scalar.yaml',
                'YAML file must contain an array/map, got string'
            )
        );

        $loader->load();
    }

    #[TestDox('fails batch validation when an option declaration is malformed, naming the option path')]
    public function testFailsValidationForMalformedOption(): void
    {
        file_put_contents($this->tempDir . '/broken-option.yaml', "type: object\n");

        $loader = $this->createLoader([new StyleOptionSourceDirectory('core', $this->tempDir)]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/options\[broken-option\]\.type/');

        $loader->load();
    }

    #[TestDox('fails batch validation when a declaration carries an unknown kind, naming the option path')]
    public function testFailsValidationForUnknownKind(): void
    {
        file_put_contents($this->tempDir . '/bad-kind.yaml', "type: string\nkind: inline-spacing\n");

        $loader = $this->createLoader([new StyleOptionSourceDirectory('core', $this->tempDir)]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/options\[bad-kind\]\.kind/');

        $loader->load();
    }

    #[TestDox('loads a declaration whose kind is box-spacing')]
    public function testLoadsBoxSpacingKind(): void
    {
        file_put_contents($this->tempDir . '/padding.yaml', "type: string\nkind: box-spacing\n");

        $options = $this->createLoader([new StyleOptionSourceDirectory('core', $this->tempDir)])->load();

        static::assertCount(1, $options);
        static::assertSame('box-spacing', $options[0]->kind());
    }

    /**
     * @param list<StyleOptionSourceDirectory> $directories
     */
    private function createLoader(array $directories): YamlStyleOptionLoader
    {
        return new YamlStyleOptionLoader(
            new StyleOptionSpecificationSerializer(),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            $directories,
        );
    }
}
