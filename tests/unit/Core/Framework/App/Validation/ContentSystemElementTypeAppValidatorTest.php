<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Validation\ContentSystemElementTypeAppValidator;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemElementTypeSchemaError;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemElementTypeAppValidator::class)]
class ContentSystemElementTypeAppValidatorTest extends TestCase
{
    #[TestDox('returns no errors when types directory is valid')]
    public function testReturnsNoErrorsWhenTypesDirectoryIsValid(): void
    {
        $loader = static::createStub(YamlTypeLoader::class);

        $manifest = $this->buildManifest('/app/path', 'TestApp');

        $validator = new ContentSystemElementTypeAppValidator($loader);
        $errors = $validator->validate($manifest, Context::createDefaultContext());

        static::assertCount(0, $errors->getElements());
    }

    #[TestDox('returns schema error when types directory contains invalid definitions')]
    public function testReturnsSchemaErrorWhenTypesDirectoryIsInvalid(): void
    {
        $loader = static::createStub(YamlTypeLoader::class);
        $loader->method('loadFromDirectory')
            ->willThrowException(ContentSystemException::elementTypeLoadFailed('broken.yaml', 'Invalid YAML syntax'));

        $manifest = $this->buildManifest('/app/path', 'TestApp');

        $validator = new ContentSystemElementTypeAppValidator($loader);
        $errors = $validator->validate($manifest, Context::createDefaultContext());

        static::assertCount(1, $errors->getElements());

        $error = $errors->first();
        static::assertInstanceOf(ContentSystemElementTypeSchemaError::class, $error);
        static::assertSame(
            'Invalid element type schema in "/app/path/Resources/content-system/types": Failed to load element type from "broken.yaml": Invalid YAML syntax',
            $error->getMessage()
        );
        static::assertSame('manifest-invalid-element-type-schema', $error->getMessageKey());
    }

    #[TestDox('propagates non-content-system exceptions without catching')]
    public function testPropagatesNonContentSystemExceptions(): void
    {
        $loader = static::createStub(YamlTypeLoader::class);
        $loader->method('loadFromDirectory')
            ->willThrowException(new \RuntimeException('Unexpected filesystem error'));

        $manifest = $this->buildManifest('/app/path', 'TestApp');

        $validator = new ContentSystemElementTypeAppValidator($loader);

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
