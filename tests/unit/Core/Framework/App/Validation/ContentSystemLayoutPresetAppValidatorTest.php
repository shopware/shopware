<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Validation\ContentSystemLayoutPresetAppValidator;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemLayoutPresetSchemaError;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\YamlLayoutPresetLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemLayoutPresetAppValidator::class)]
class ContentSystemLayoutPresetAppValidatorTest extends TestCase
{
    #[TestDox('returns no errors when the presets directory is valid')]
    public function testReturnsNoErrorsWhenPresetsDirectoryIsValid(): void
    {
        $loader = static::createStub(YamlLayoutPresetLoader::class);

        $manifest = $this->buildManifest('/app/path', 'TestApp');

        $validator = new ContentSystemLayoutPresetAppValidator($loader);
        $errors = $validator->validate($manifest, Context::createDefaultContext());

        static::assertCount(0, $errors->getElements());
    }

    #[TestDox('returns a schema error when the presets directory contains invalid definitions')]
    public function testReturnsSchemaErrorWhenPresetsDirectoryIsInvalid(): void
    {
        $loader = static::createStub(YamlLayoutPresetLoader::class);
        $loader->method('readRawFromDirectory')
            ->willThrowException(ContentSystemException::layoutPresetLoadFailed('broken.yaml', 'Invalid YAML syntax'));

        $manifest = $this->buildManifest('/app/path', 'TestApp');

        $validator = new ContentSystemLayoutPresetAppValidator($loader);
        $errors = $validator->validate($manifest, Context::createDefaultContext());

        static::assertCount(1, $errors->getElements());

        $error = $errors->first();
        static::assertInstanceOf(ContentSystemLayoutPresetSchemaError::class, $error);
        static::assertSame(
            'Invalid layout preset in "/app/path/Resources/content-system/presets": Failed to load layout preset from "broken.yaml": Invalid YAML syntax',
            $error->getMessage()
        );
        static::assertSame('manifest-invalid-layout-preset-schema', $error->getMessageKey());
    }

    #[TestDox('propagates non-content-system exceptions without catching')]
    public function testPropagatesNonContentSystemExceptions(): void
    {
        $loader = static::createStub(YamlLayoutPresetLoader::class);
        $loader->method('readRawFromDirectory')
            ->willThrowException(new \RuntimeException('Unexpected filesystem error'));

        $manifest = $this->buildManifest('/app/path', 'TestApp');

        $validator = new ContentSystemLayoutPresetAppValidator($loader);

        $this->expectExceptionObject(new \RuntimeException('Unexpected filesystem error'));
        $validator->validate($manifest, Context::createDefaultContext());
    }

    private function buildManifest(string $path, string $appName): Manifest
    {
        $metadata = static::createStub(Metadata::class);
        $metadata->method('getName')->willReturn($appName);

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getPath')->willReturn($path);
        $manifest->method('getMetadata')->willReturn($metadata);

        return $manifest;
    }
}
