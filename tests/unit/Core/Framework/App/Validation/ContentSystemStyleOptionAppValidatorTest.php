<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Validation\ContentSystemStyleOptionAppValidator;
use Shopware\Core\Framework\App\Validation\Error\ContentSystemStyleOptionSchemaError;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemStyleOptionAppValidator::class)]
class ContentSystemStyleOptionAppValidatorTest extends TestCase
{
    #[TestDox('returns no errors when the style options directory is valid')]
    public function testReturnsNoErrorsWhenDirectoryIsValid(): void
    {
        $validator = new ContentSystemStyleOptionAppValidator(static::createStub(YamlStyleOptionLoader::class));

        $errors = $validator->validate($this->buildManifest('/app/path', 'TestApp'), Context::createDefaultContext());

        static::assertCount(0, $errors->getElements());
    }

    #[TestDox('returns a schema error when the style options directory is invalid')]
    public function testReturnsSchemaErrorWhenDirectoryIsInvalid(): void
    {
        $loader = static::createStub(YamlStyleOptionLoader::class);
        $loader->method('loadDtosFromDirectory')
            ->willThrowException(ContentSystemException::styleOptionLoadFailed('broken.yaml', 'Invalid YAML syntax'));

        $validator = new ContentSystemStyleOptionAppValidator($loader);
        $errors = $validator->validate($this->buildManifest('/app/path', 'TestApp'), Context::createDefaultContext());

        static::assertCount(1, $errors->getElements());

        $error = $errors->first();
        static::assertInstanceOf(ContentSystemStyleOptionSchemaError::class, $error);
        static::assertSame(
            'Invalid style option schema in "/app/path/Resources/content-system/style-options": Failed to load style option from "broken.yaml": Invalid YAML syntax',
            $error->getMessage()
        );
        static::assertSame('manifest-invalid-style-option-schema', $error->getMessageKey());
    }

    #[TestDox('propagates non-content-system exceptions without catching them')]
    public function testPropagatesNonContentSystemExceptions(): void
    {
        $loader = static::createStub(YamlStyleOptionLoader::class);
        $loader->method('loadDtosFromDirectory')->willThrowException(new \RuntimeException('Unexpected filesystem error'));

        $validator = new ContentSystemStyleOptionAppValidator($loader);

        $this->expectExceptionObject(new \RuntimeException('Unexpected filesystem error'));
        $validator->validate($this->buildManifest('/app/path', 'TestApp'), Context::createDefaultContext());
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
